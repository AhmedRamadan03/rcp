<?php

namespace App\Http\Controllers\Api\V1\Deleted;

use App\Enums\ClaimStatus;
use App\Enums\Roles;
use App\Models\Claim;
use App\Models\PaymentLink;
use App\Models\User;
use App\Services\TargetReportService;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    /**
     * Get dashboard data
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $targetReportService = new TargetReportService;
        $data = $targetReportService->getTopTarget();
        $categories = $targetReportService->getMonths($data);

        $response = [
            'data' => $data,
            'categories' => $categories,
        ];

        if(config('app.client_name') === "ART") {
            $claims = Claim::all();
            $total_claims = $claims->count();
            $approved_claims = $claims->where('status', 'approved')->count();
            $approved_amount = $claims->where('status', 'approved')->sum('amount');
            $rejected_claims = $claims->where('status', 'rejected')->count();
            $rejected_amount = $claims->where('status', 'rejected')->sum('amount');
            $rigstered_claims = $claims->where('status', 'registered')->count();
            $registered_amount = $claims->where('status', 'registered')->sum('amount');
            $transformed_to_lawyer_claims = $claims->where('status', 'transformed_to_lawyer')->count();
            $transformed_to_lawyer_amount = $claims->where('status', 'transformed_to_lawyer')->sum('amount');
            $transformed_to_elm = $claims->where('status', 'transfer_to_elm')->count();
            $transformed_to_elm_amount = $claims->where('status', 'transfer_to_elm')->sum('amount');
            $total_amount = $claims->sum('amount');
            $closed_claims = $claims->where('is_close', 1)->count();
            $closed_amount = $claims->where('is_close', 1)->sum('amount');

            $collected_claims = PaymentLink::where('send_status', 'paid')
                ->where('type', 'installment_payment')
                ->selectRaw("sum(amount) as amount_sum, count(*) as count")->first();

            $delayed_claims = PaymentLink::where('type', 'delay_payment')
                ->selectRaw("sum(amount) as amount_sum, count(*) as count")->first();

            $full_payment_claims = PaymentLink::where('send_status', 'paid')
                ->where('type', 'full_payment')
                ->selectRaw("sum(amount) as amount_sum, count(*) as count")->first();

            $partial_collected_claims = $claims->whereIn('status', [
                ClaimStatus::FOLLOW_UP->value,
                ClaimStatus::PARTIAL->value
            ])->sum("amount");

            $default_in_payment_claims = $claims->whereIn('status', [
                ClaimStatus::DEFAULT_IN_PAYMENT->value,
                ClaimStatus::DEFAULT_IN_PAYMENT_BY_SYSTEM->value
            ])->sum("amount");

            $follow_up_amount = $claims->whereIn('status', [
                ClaimStatus::FOLLOW_UP->value,
            ])->sum("amount");

            $super_admin_users = User::where('role_id', Roles::SUPER_ADMIN->value)->count();
            $super_visor_users = User::where('role_id', Roles::SUPERVISOR->value)->count();
            $collector_users = User::where('role_id', Roles::COLLECTOR->value)->count();
            $external_office_users = User::where('role_id', Roles::EXTERNAL_OFFICE->value)->count();
            $legal_department_users = User::where('role_id', Roles::LEGAL_DEPARTMENT->value)->count();
            $taheiya_admin_users = User::where('role_id', Roles::TAHEIYA_ADMIN->value)->count();
            $others_users = User::where('role_id', Roles::OTHERS->value)->count();

            $response['claims_data'] = [
                'claims' => $claims->count(),
                'total_claims' => $total_claims,
                'approved_claims' => $approved_claims,
                'approved_amount' => $approved_amount,
                'rejected_claims' => $rejected_claims,
                'rejected_amount' => $rejected_amount,
                'rigstered_claims' => $rigstered_claims,
                'registered_amount' => $registered_amount,
                'transformed_to_lawyer_claims' => $transformed_to_lawyer_claims,
                'transformed_to_lawyer_amount' => $transformed_to_lawyer_amount,
                'transformed_to_elm' => $transformed_to_elm,
                'transformed_to_elm_amount' => $transformed_to_elm_amount,
                'total_amount' => $total_amount,
                'closed_claims' => $closed_claims,
                'collected_claims' => $collected_claims,
                'delayed_claims' => $delayed_claims,
                'full_payment_claims' => $full_payment_claims,
                'partial_collected_claims' => $partial_collected_claims,
                'default_in_payment_claims' => $default_in_payment_claims,
                'follow_up_amount' => $follow_up_amount,
                'closed_amount' => $closed_amount,
                'users' => [
                    'super_admin_users' => $super_admin_users,
                    'super_visor_users' => $super_visor_users,
                    'collector_users' => $collector_users,
                    'external_office_users' => $external_office_users,
                    'legal_department_users' => $legal_department_users,
                    'taheiya_admin_users' => $taheiya_admin_users,
                    'others_users' => $others_users,
                ]
            ];
        }

        return response()->json([
            'error' => false,
            'message' => 'Dashboard data retrieved successfully',
            'data' => $response
        ]);
    }
}
