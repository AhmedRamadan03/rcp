<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ClaimStatus;
use App\Enums\DataHistoryEnum;
use App\Enums\Roles;
use App\Http\Controllers\Controller;
use App\Http\Requests\Request;
use App\Http\Resources\Api\V1\Dashboard\AssignedClaimResource;
use App\Http\Resources\Api\V1\Dashboard\TargetDashboardResouorce;
use App\Models\Claim;
use App\Models\DataHistory;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\PaymentTransaction;
use App\Models\Target;
use App\Models\User;
use App\Services\Dashboard\AssignedClaimService;
use App\Services\Dashboard\AssignedClaimsReport\AssignedClaimsReportFilters;
use App\Services\Dashboard\AssignedClaimsReport\AssignedClaimsReportQuery;
use App\Services\Dashboard\AssignedClaimsReportService;
use App\Services\Dashboard\CollectedClaimAmount;
use App\Services\Dashboard\StatusReport\StatusReportFilters;
use App\Services\Dashboard\StatusReport\StatusReportQuery;
use App\Services\Dashboard\StatusReportService;
use App\Services\TargetReportService;
use Carbon\Carbon;
use DateInterval;
use DatePeriod;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{


    /**
     * Returns the assigned claims for a given type.
     *
     * @param Request $request
     * @return array
     */
    public function assignedClaims(Request $request)
    {
        // Convert between the two entity roleIds in case of role and checkUserIds in case of user or default
        $entity = $request->input('entity', 'user'); // user | role
        $entityIds = match ($entity) {
            'role' => $request->input('roleIds', []),
            default => $this->checkUserIds($request)
        };

        $type    = $request->input('type', 'month'); // year | month | day
        $date    = $request->input('date', now()->format('Y-m-d')); // يوم أو سنة حسب type
        return (new AssignedClaimService())->getAssignedClaims($entity, $type, $entityIds, $date);
    }

    /**
     * Assigned claims report by channel (internal, external, legal) or assignee user.
     *
     * Query params:
     * - from, to (Y-m-d) — COALESCE(intimation_date, accident_date); default current year
     * - entity — role | user; default role
     * - entity_ids[] — channel names when entity=role; user IDs when entity=user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignedClaimsReport(Request $request)
    {
        return response()->json(
            [
                'success' => true,
                'data' => (new AssignedClaimsReportService())->build($request),
            ],
            200,
            [],
            JSON_PRESERVE_ZERO_FRACTION
        );
    }

    public function exportAssignedClaimsReport(Request $request)
    {
        $fileName = 'assigned_claims_history_' . time() . '.xlsx';
        $user = auth()->user();

        $resolved = (new AssignedClaimsReportFilters())->resolve($request);
        $claimIds = (new AssignedClaimsReportQuery())->claimIds($resolved);

        $data = (new AssignedClaimsReportService())->build($request);

        $history = DataHistory::createDataHistory(
            DataHistoryEnum::EXPORT_ASSIGNED_CLAIMS_DATA->value,
            DataHistoryEnum::EXPORT->value,
            $fileName,
            $resolved
        );

        \App\Jobs\ExportAssignedClaimsHistory::dispatch(
            $claimIds,
            $user,
            $fileName,
            $data['details'],
            $data['summary'],
            $history->ref
        );

        return response()->json([
            'status' => true,
            'message' => 'Your file is being prepared. You will be notified once it is ready.',
        ]);
    }

    public function collectedClaimAmount(Request $request)
    {
        $entity = $request->input('entity', 'user'); // user | role
        $entityIds = match ($entity) {
            'role' => $request->input('roleIds', []),
            default => $this->checkUserIds($request)
        };

        $type    = $request->input('type', 'month'); // year | month | day
        $date    = $request->input('date', now()->format('Y-m-d')); // يوم أو سنة حسب type
        

        return (new CollectedClaimAmount())->getAssignedClaims($entity, $type, $entityIds, $date);
    }
    public function getYears(Request $request)
    {
        $years = DB::table('claims')
            ->select(DB::raw('DISTINCT EXTRACT(YEAR FROM created_at) as year'))
            ->orderBy('year', 'asc')
            ->pluck('year');

        return response()->json(['success' => true, 'data' => $years]);
    }
    public function paymentMethods(Request $request)
    {
        // يستقبل الفلتر لو موجود (q1, q2, q3, q4)
        $quarter = $request->input('quarter');

        // نحدد تواريخ الربع المطلوب
        $dateRange = $this->getQuarterDateRange($quarter);
        $userIds = $this->checkUserIds($request);
        // ابدأ query
        $query =PaymentTransaction::when(!in_array(auth()->user()->role_id, [Roles::SUPER_ADMIN->value, Roles::SUPERVISOR->value]), function ($query) use ($userIds) {
                    $query->whereHas('payment.claim.assignees', function ($q) use ($userIds) {
                        $q->whereIn('to', $userIds)->where('is_active', 1);
                    });
                });

        // لو فيه فلتر ربع، نطبق الفلترة
        if ($dateRange) {
            $query->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        }

        // إجمالي عدد المعاملات
        $total = $query->count();

        // لو مفيش بيانات في الربع المطلوب
        if ($total == 0) {
            return response()->json([
                "success" => true,
                "data" => [
                    'manual_percentage' => 0,
                    'gateway_percentage' => 0
                ]
            ]);
        }

        // حساب عدد كل نوع
        $manualCount = (clone $query)
            ->where('payment_type', 'manual')
            ->count();

        $gatewayCount = (clone $query)
            ->where('payment_type', '!=', 'manual')
            ->count();

        // حساب النسب
        $manualPercentage = ($manualCount / $total) * 100;
        $gatewayPercentage = ($gatewayCount / $total) * 100;

        $response = [
            'manual_percentage' => round($manualPercentage, 2),
            'gateway_percentage' => round($gatewayPercentage, 2),
        ];

        return response()->json([
            "success" => true,
            "data" => $response
        ]);
    }
    public function employeeTarget(Request $request)
    {
        // فلتر الربع (q1, q2, q3, q4)
        $quarter = $request->input('quarter');

        // الحصول على نطاق التاريخ حسب الربع
        $dateRange = $this->getQuarterDateRange($quarter);
        $userIds = $this->checkUserIds($request);
        // ابدأ Query
        $query = Target::when(!in_array(auth()->user()->role_id, [Roles::SUPER_ADMIN->value, Roles::SUPERVISOR->value]), function ($query) use ($userIds) {

                        $query->whereIn('user_id', $userIds);

                });

        // تطبيق الفلتر إذا تم إرسال quarter
        if ($dateRange) {
            $query->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        }

        // إحضار أعلى 5
        $topEmployees = $query
            ->orderBy('achieved', 'desc')
               // الأكبر أولاً
            ->take(5)
            ->get();

        return response()->json([
            "success" => true,
            "data" => TargetDashboardResouorce::collection($topEmployees)
        ]);
    }






    public function claimStatus(Request $request)
    {
        // ------------------------------
        // 1️⃣ تحديد الفلتر الزمني
        // ------------------------------
        $filter = $request->type ?? 'all'; // default
        $userIds = $this->checkUserIds($request);

        $query = Claim::when(!in_array(auth()->user()->role_id, [Roles::SUPER_ADMIN->value, Roles::SUPERVISOR->value]), function ($query) use ($userIds) {
                    $query->whereHas('assignees', function ($q) use ($userIds) {
                        $q->whereIn('to', $userIds)->where('is_active', 1);
                    });
                });
               
        $paymentQuery = Payment::when(!in_array(auth()->user()->role_id, [Roles::SUPER_ADMIN->value, Roles::SUPERVISOR->value]), function ($query) use ($userIds) {
                    $query->whereHas('claim.assignees', function ($q) use ($userIds) {
                        $q->whereIn('to', $userIds)->where('is_active', 1);
                    });
                });

        switch ($filter) {
            case 'last_year':
                $query->whereYear('created_at', Carbon::now()->subYear()->year);
                $paymentQuery->whereYear('created_at', Carbon::now()->subYear()->year);
                break;

            case 'current_month':
                $query->whereMonth('created_at', Carbon::now()->month)
                    ->whereYear('created_at', Carbon::now()->year);
                $paymentQuery->whereMonth('created_at', Carbon::now()->month)
                    ->whereYear('created_at', Carbon::now()->year);
                break;

            case 'last_month':
                $lastMonth = Carbon::now()->subMonth();
                $query->whereMonth('created_at', $lastMonth->month)
                    ->whereYear('created_at', $lastMonth->year);
                $paymentQuery->whereMonth('created_at', $lastMonth->month)
                    ->whereYear('created_at', $lastMonth->year);
                break;

            case 'last_week':
                $query->whereBetween('created_at', [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]);
                $paymentQuery->whereBetween('created_at', [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]);
                break;

            case 'today':
                $query->whereDate('created_at', Carbon::today());
                $paymentQuery->whereDate('created_at', Carbon::today());
                break;

            case 'current_year':
            default:
                $query->whereYear('created_at', Carbon::now()->year);
                $paymentQuery->whereYear('created_at', Carbon::now()->year);
                break;
        }

        // ------------------------------
        // 2️⃣ حساب إجمالي عدد الـ Claims
        // ------------------------------
        $totalClaims = $query->count();

        if ($totalClaims === 0) {
            return response()->json([
                "success" => true,
                "data" => []
            ]);
        }

        // ------------------------------
        // 3️⃣ عدد كل حالة ونسبة مئوية
        // ------------------------------
        $statuses = $query->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();

        $response = $statuses->mapWithKeys(function ($item) use ($totalClaims) {
            return [
                $item->status => [
                    "count" => $item->count,
                    "percentage" => round(($item->count / $totalClaims) * 100, 2)
                ]
            ];
        });

        // ------------------------------
        // 4️⃣ إجمالي المطلوب والمدفوع من Payment
        // ------------------------------
        $totals = $paymentQuery->selectRaw('SUM(amount) as required_amount, SUM(paid_amount) as collected_amount')
            ->first();

        $response['total'] = [
            "required"  => (float) ($totals->required_amount ?? 0),
            "collected" => (float) ($totals->collected_amount ?? 0),
        ];

        return response()->json([
            "success" => true,
            "filter" => $filter,
            "data" => $response
        ]);
    }



    public function MonthlyClaimDetails(Request $request)
    {
        $type = $request->type ?? 'month';
        $year = $request->year ?? date('Y');
        // أسماء الأشهر
        $monthsMap = [
            1 => "January",
            2 => "February",
            3 => "March",
            4 => "April",
            5 => "May",
            6 => "June",
            7 => "July",
            8 => "August",
            9 => "September",
            10 => "October",
            11 => "November",
            12 => "December",
        ];

        $userIds = $this->checkUserIds($request);
        if ($type === 'year') {
            return $this->getYearData($request, $year, $userIds);
        }
        if ($type === 'month') {
            return $this->getMonthData($monthsMap, $year, $userIds);
        }
        if ($type === 'quarter') {
            return $this->getQuarterData($monthsMap, $year, $request, $userIds);
        }
    }

    /**
     * Status report comparing cleansed / collected / outstanding metrics
     * by channel (internal, external, legal) or by assignee user.
     *
     * Query params:
     * - from, to (Y-m-d) — COALESCE(intimation_date, accident_date); default current year
     * - entity — role | user; default role
     * - entity_ids[] — channel names (internal|external|legal) when entity=role; user IDs when entity=user
     * - status[] — cleansed | collect | outstanding
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function statusReport(Request $request)
    {
        return response()->json(
            [
                'success' => true,
                'data' => (new StatusReportService())->build($request),
            ],
            200,
            [],
            JSON_PRESERVE_ZERO_FRACTION
        );
    }

    public function exportStatusReport(Request $request)
    {
        $fileName = 'status_report_export_' . time() . '.xlsx';
        $user = auth()->user();

        $resolved = (new StatusReportFilters())->resolve($request);
        $claimIds = (new StatusReportQuery())->claimIds($resolved);

        $data = (new StatusReportService())->build($request);
        $history = DataHistory::createDataHistory(
            DataHistoryEnum::CLAIM_EXPORT->value,
            DataHistoryEnum::EXPORT->value,
            $fileName,
            $resolved
        );

        \App\Jobs\ExportStatusReportClaims::dispatch(
            $claimIds,
            $user,
            $fileName,
            $data['details'],
            $data['summary'],
            $history->ref
        );

        return response()->json([
            'status' => true,
            'message' => 'Your file is being prepared. You will be notified once it is ready.',
        ]);
    }


    public function exportStatusReportTransactions(Request $request)
    {
        $fileName = 'status_report_transactions_' . time() . '.xlsx';
        $user = auth()->user();

        $resolved = (new StatusReportFilters())->resolve($request);
        $claimIds = (new StatusReportQuery())->claimIds($resolved);

        $data = (new StatusReportService())->build($request);

        $history = DataHistory::createDataHistory(
            DataHistoryEnum::EXPORT_TRANSACTION_HISTORY->value,
            DataHistoryEnum::EXPORT->value,
            $fileName,
            $resolved
        );

        \App\Jobs\ExportStatusReportTransactions::dispatch(
            $claimIds,
            $user,
            $fileName,
            $data['details'],
            $data['summary'],
            $history->ref
        );

        return response()->json([
            'status' => true,
            'message' => 'Your file is being prepared. You will be notified once it is ready.',
        ]);
    }

    private function getYearData($request, $year, $userIds)
    {
        $response = [];

        if ($request->has('year')) {
            // السنة محددة
            $years = [(int) $year];
        } else {
            // لو مفيش سنة محددة، نجيب كل السنوات الموجودة
            $years = Payment::selectRaw('DISTINCT EXTRACT(YEAR FROM created_at) as year')
                ->orderBy('year', 'desc')
                ->get()
                ->pluck('year')
                ->map(fn($y) => (int)$y)
                ->toArray();
        }

        foreach ($years as $y) {

            // حساب إجمالي السنة
            $totals = Payment::selectRaw('
                SUM(amount) as required_amount,
                SUM(paid_amount) as collected_amount
            ')
                ->whereRaw('EXTRACT(YEAR FROM created_at) = ?', [$y])
                ->when(!in_array(auth()->user()->role_id, [Roles::SUPER_ADMIN->value, Roles::SUPERVISOR->value]), function ($query) use ($userIds) {
                    $query->whereHas('claim.assignees', function ($q) use ($userIds) {
                        $q->whereIn('to', $userIds)->where('is_active', 1);
                    });
                })
                ->first();

            $response[$y] = [
                "required"  => (float) ($totals->required_amount ?? 0),
                "collected" => (float) ($totals->collected_amount ?? 0),
            ];
        }

        return response()->json([
            "success" => true,
            "data" => $response
        ]);
    }
    private function getMonthData($monthsMap, $year,$userIds)
    {
        $response = [];
        foreach ($monthsMap as $m) {
            $response[$m] = [];
        }

        $records = Payment::selectRaw('
                SUM(amount) as required_amount,
                SUM(paid_amount) as collected_amount,
                EXTRACT(MONTH FROM created_at) as month
            ')
            ->whereRaw('EXTRACT(YEAR FROM created_at) = ?', [$year])
             ->when(!in_array(auth()->user()->role_id, [Roles::SUPER_ADMIN->value, Roles::SUPERVISOR->value]), function ($query) use ($userIds) {
                    $query->whereHas('claim.assignees', function ($q) use ($userIds) {
                        $q->whereIn('to', $userIds)->where('is_active', 1);
                    });
                })
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        foreach ($records as $rec) {
            $monthName = $monthsMap[(int)$rec->month];

            $response[$monthName] = [
                "required"  => $rec->required_amount,
                "collected" => $rec->collected_amount,
            ];
        }

        return response()->json([
            "success" => true,
            "year" => $year,
            "data" => $response
        ]);
    }
    private function getQuarterData($monthsMap, $year, $request, $userIds)
    {
        // quarter رقم (1,2,3,4)
        $selectedQuarter = (int) ($request->quarter ?? 1);

        $quarters = [
            1 => [1, 2, 3],      // Q1
            2 => [4, 5, 6],      // Q2
            3 => [7, 8, 9],      // Q3
            4 => [10, 11, 12],   // Q4
        ];

        // لو الربع غلط رجع Error
        if (!isset($quarters[$selectedQuarter])) {
            return response()->json([
                "success" => false,
                "message" => "Quarter must be between 1 - 4"
            ]);
        }



        // جهز الريسبونس الفارغ للشهور
        $response = [];
        foreach ($quarters[$selectedQuarter] as $m) {
            $response[$monthsMap[$m]] = [
                "required" => 0,
                "collected" => 0
            ];
        }

        // جلب البيانات من الداتابيز لكل شهر
        $records = Payment::selectRaw('
            EXTRACT(MONTH FROM created_at) as month,
            SUM(amount) as required_amount,
            SUM(paid_amount) as collected_amount
        ')
            ->whereRaw('EXTRACT(YEAR FROM created_at) = ?', [$year])
            ->whereRaw('EXTRACT(MONTH FROM created_at) IN (' . implode(",", $quarters[$selectedQuarter]) . ')')
             ->when(!in_array(auth()->user()->role_id, [Roles::SUPER_ADMIN->value, Roles::SUPERVISOR->value]), function ($query) use ($userIds) {
                    $query->whereHas('claim.assignees', function ($q) use ($userIds) {
                        $q->whereIn('to', $userIds)->where('is_active', 1);
                    });
                })
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        foreach ($records as $rec) {
            $monthNumber = (int) $rec->month;
            $monthName = $monthsMap[$monthNumber];

            $response[$monthName] = [
                "required"  => (float) $rec->required_amount,
                "collected" => (float) $rec->collected_amount,
            ];
        }

        return response()->json([
            "success" => true,
            "quarter" => "Q" . $selectedQuarter,
            "year" => $year,
            "data" => $response,
        ]);
    }

    private function getQuarterDateRange($quarter)
    {
        $year = now()->year;

        switch ($quarter) {
            case 'q1':
                return [
                    'start' => "$year-01-01",
                    'end'   => "$year-03-31"
                ];
            case 'q2':
                return [
                    'start' => "$year-04-01",
                    'end'   => "$year-06-30"
                ];
            case 'q3':
                return [
                    'start' => "$year-07-01",
                    'end'   => "$year-09-30"
                ];
            case 'q4':
                return [
                    'start' => "$year-10-01",
                    'end'   => "$year-12-31"
                ];
            default:
                return null; // لو مفيش فلتر
        }
    }

    private function checkUserIds($request)
    {
        $userIds = $request->input('user_ids', []);
        if (!in_array(auth()->user()->role_id, [Roles::SUPER_ADMIN->value, Roles::SUPERVISOR->value])) {
            $userIds = [auth()->user()->id];
        }
        if (auth()->user()->role_id == Roles::SENIOR_COLLECTOR->value) {
            $userIds = User::where('supervisor_id', auth()->user()->id)->pluck('id')->toArray();
            $userIds = array_merge($userIds, [auth()->user()->id]);
        }

        return $userIds;
    }
}