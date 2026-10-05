<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RevertAssignClaimsAction;
use App\Actions\RevertBilledClaimsAction;
use App\Actions\RevertImportedBacklogClaimsAction;
use App\Enums\ClaimStatus;
use App\Enums\DataHistoryEnum;
use App\Enums\ExportType;
use App\Enums\Roles;
use App\Exports\AuthLogExport;
use App\Exports\ClaimExports;
use App\Exports\CollectorTargetsExport;
use App\Exports\MessageExport;
use App\Exports\RejectedClaims;
use App\Exports\TargetDataExport;
use App\Exports\TargetExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\ExportCollectorTargetRequest;
use App\Http\Requests\V1\AuthenticationLogs\AuthenticationLogFilterRequest;
use App\Http\Requests\V1\ExportClaimsAssignedRequest;
use App\Http\Requests\V1\ExportDataHistoryRequest;
use App\Http\Requests\V1\ExportMessagesRequest;
use App\Http\Requests\V1\ExportTargetDetailsRequest;
use App\Http\Requests\V1\ExportTargetReportRequest;
use App\Http\Requests\V1\Reports\DefaultOnPaymentFilterRequest;
use App\Http\Resources\Api\V1\AssignClaims\AssignedHistoryResource;
use App\Http\Resources\Api\V1\AssignClaims\AssigniesHistoryResource;
use App\Http\Resources\Api\V1\Reports\AuthenticationLogReportResource;
use App\Http\Resources\Api\V1\Reports\CollectorTargetsReportResource;
use App\Jobs\ExportAuthenticationLogsOpenSpout;
use App\Jobs\ExportDefaultOnPaymentOpenSpout;
use App\Models\AuthenticationLog;
use App\Models\Claim;
use App\Models\Target;
use App\Models\User;
use App\Services\ClaimService;
use App\Services\DefaultOnPaymentService;
use App\Services\RejectionService;
use App\Services\TargetReportService;
use App\Services\TargetService;
use App\Http\Resources\Api\V1\Reports\DefaultOnPaymentReportResource;
use App\Traits\ClaimFilters;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Http\Resources\Api\V1\Collections\Collection as CollectionResource;
use App\Http\Resources\Api\V1\Reports\CollectorsTargetsReportResource;
use App\Models\Assigning;
use App\Models\DataHistory;
use App\Models\Message;
use App\Services\AuthenticationLogService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReportsController extends Controller
{
    use ClaimFilters;
    private const DEFAULT_ON_PAYMENT = 'default_on_payment';
    private const REJECTED_CLAIMS = 'rejected_claims';
    private const TARGETS = 'targets';

    private ClaimService $claimService;
    private RejectionService $rejectionService;
    private TargetReportService $targetReportService;

    public function __construct(
        ClaimService $claimService,
        RejectionService $rejectionService,
        TargetReportService $targetReportService
    ) {
        $this->claimService = $claimService;
        $this->rejectionService = $rejectionService;
        $this->targetReportService = $targetReportService;
    }



    public function rejectedClaims(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 10);
        $searchNumber = $request->input('search', '');

        $serviceList = $this->rejectionService->list([
            'not_seen' => $request->boolean('not_seen'),
            'accepted' => $request->boolean('accepted'),
            'rejected' => $request->boolean('rejected'),
            'claim_number' => '',
            'debtor_phone' => '',
            'policy_number' => '',
            'amount' => '',
        ], $perPage, empty($searchNumber));

        if (!empty($searchNumber)) {
            $serviceList = $this->filterRejectedClaims($serviceList, $searchNumber, $perPage);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rejected claims retrieved successfully',
            'data' => [
                'rejected_claims' => $serviceList->items(),
                'pagination' => [
                    'current_page' => $serviceList->currentPage(),
                    'last_page' => $serviceList->lastPage(),
                    'per_page' => $serviceList->perPage(),
                    'total' => $serviceList->total()
                ]
            ]
        ]);
    }

    public function targets(ExportTargetReportRequest $request): JsonResponse
    {
        $perPage = $request->input('per_page', 10);
        $targets = $this->targetReportService->getCollectorsWithAllData($perPage,null,false,$request->filters());

        return response()->json([
            'success' => true,
            'message' => 'Targets retrieved successfully',
            'data' => [
                'targets' => CollectorsTargetsReportResource::Collection($targets),
                'pagination' => [
                    'current_page' => $targets->currentPage(),
                    'last_page' => $targets->lastPage(),
                    'per_page' => $targets->perPage(),
                    'total' => $targets->total()
                ]
            ]
        ]);
    }


    public function target($user_id, ExportCollectorTargetRequest $request): JsonResponse
    {
        $perPage = $request->input('per_page', 10);
        $targetsHistory = (new TargetService)->getOfficerTargetHistory($user_id, $perPage,false,$request->filters());
        return response()->json([
            'success' => true,
            'message' => 'Targets retrieved successfully',
            'data' => [
                'targets' => CollectorTargetsReportResource::Collection($targetsHistory),
                'pagination' => [
                    'current_page' => $targetsHistory->currentPage(),
                    'last_page' => $targetsHistory->lastPage(),
                    'per_page' => $targetsHistory->perPage(),
                    'total' => $targetsHistory->total()
                ]
            ]
        ]);
    }


    public function targetData(ExportTargetDetailsRequest $request, $target_id)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $data = $this->targetReportService->getTargetData($target_id, $perPage, false,  $request->filters());

            return response()->json([
                'success' => true,
                'message' => 'Target Data',
                'data' => [
                    'rows' => $data->items(),
                    'pagination' => [
                        'current_page' => $data->currentPage(),
                        'last_page' => $data->lastPage(),
                        'per_page' => $data->perPage(),
                        'total' => $data->total(),
                    ]
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Target Not Found'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }


    public function authenticationLog(AuthenticationLogFilterRequest $request): JsonResponse
    {
        $authLogs = (new AuthenticationLogService)->list($request->filters(), $request->perPage());

        return response()->json(
            new CollectionResource($authLogs, $message = 'Authentication logs returned successfully', $resourceClass = AuthenticationLogReportResource::class)
        );
    }

    public function acceptRejection(int $rejectedClaimId): JsonResponse
    {
        try {
            $this->rejectionService->acceptRejection($rejectedClaimId);

            return response()->json([
                'success' => true,
                'message' => __('claim.rejection_has_been_accepted_successfully'),
                'data' => null
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ], 422);
        }
    }

    public function rejectRejection(int $rejectedClaimId): JsonResponse
    {
        try {
            $this->rejectionService->rejectRejection($rejectedClaimId);

            return response()->json([
                'success' => true,
                'message' => __('claim.rejection_has_been_rejected_successfully'),
                'data' => null
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ], 422);
        }
    }


    public function defaultOnPayment(DefaultOnPaymentFilterRequest $request): JsonResponse
    {
        $result = (new DefaultOnPaymentService)->list($request->filters(), $request->perPage() ?? 10);

        return response()->json([
            'success' => true,
            'message' => 'Default on payment claims retrieved successfully',
            'data' => [
                'claims' => DefaultOnPaymentReportResource::collection($result->items()),
                'pagination' => [
                    'current_page' => $result->currentPage(),
                    'last_page' => $result->lastPage(),
                    'per_page' => $result->perPage(),
                    'total' => $result->total(),
                ],
            ],
        ]);
    }

    public function exportDefaultOnPayment(DefaultOnPaymentFilterRequest $request): JsonResponse
    {
        $filters = $request->filters();
        $columns = $request->columns();

        $fileName = 'default_on_payment_claims_' . time() . '.xlsx';

        $history = DataHistory::createDataHistory(
            DataHistoryEnum::EXPORT_DEFAULT_ON_PAYMENT_REPORT->value,
            DataHistoryEnum::EXPORT->value,
            $fileName,
            $filters
        );

        ExportDefaultOnPaymentOpenSpout::dispatch($filters, $columns, auth()->user(), $fileName, $history->ref);

        return response()->json([
            'status' => true,
            'message' => 'Your file is being prepared. You will be notified once it is ready.',
        ]);
    }

    public function exportRejectedClaims(Request $request): BinaryFileResponse
    {
        $rejectionService = new RejectionService;

        $serviceList = $rejectionService->list([
            'not_seen' => $request->not_seen,
            'accepted' => $request->accepted,
            'rejected' => $request->rejected,
            'claim_number' => '',
            'debtor_phone' => '',
            'policy_number' => '',
            'amount' => '',
        ], false, false);


        $rejected_claims = $serviceList;

        // Reuse existing export logic
        return
            Excel::download(new RejectedClaims($rejected_claims), 'claims.xlsx');
    }

    public function exportTargets(ExportTargetReportRequest $request)
    {
        $fileName = 'targets_report_' . time() . '.xlsx';
        
        $history = DataHistory::createDataHistory(DataHistoryEnum::EXPORT_TARGET_REPORT->value, DataHistoryEnum::EXPORT->value,'', $request->filters());
        \App\Jobs\ExportTargetReportOpenSpout::dispatch(auth()->user(), $fileName, $history->ref, $request->filters(), $request->columns());

        return $this->successResponse('Your file is being prepared. You will be notified once it is ready.');
       
    }

    public function exportCollectorTargets(ExportCollectorTargetRequest $request, $user_id)
    {
        $user = User::findOrFail($user_id);
        $fileName = $user->name . '_target_' . time() . '.xlsx';
        $history = DataHistory::createDataHistory(DataHistoryEnum::EXPORT_TARGET_REPORT->value, DataHistoryEnum::EXPORT->value, '', $request->filters());
        
        \App\Jobs\ExportCollectorTargetsOpenSpout::dispatch(auth()->user(),$user_id, $fileName, $history->ref, $request->filters(), $request->columns());

        return $this->successResponse('Your file is being prepared. You will be notified once it is ready.');
    }

    public function exportTargetData(ExportTargetDetailsRequest $request, $target_id)
    {
        $fileName = 'target_data_' . time() . '.xlsx';
        $history = DataHistory::createDataHistory(DataHistoryEnum::EXPORT_TARGET_DATA->value, DataHistoryEnum::EXPORT->value, '', $request->filters());
        

        \App\Jobs\ExportTargetDataOpenSpout::dispatch(auth()->user(),$target_id, $fileName, $history->ref, $request->filters(), $request->columns());

        return $this->successResponse('Your file is being prepared. You will be notified once it is ready.');
        // $targets = $this->targetReportService->getTargetData($target_id);
        // return
        //     Excel::download(new TargetDataExport($targets->values()), 'targets.xlsx', \Maatwebsite\Excel\Excel::XLSX, [
        //         'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        //     ]);
    }


    public function exportAuthenticationLog(AuthenticationLogFilterRequest $request)
    {
        $filters = $request->filters();
        $columns = $request->columns();

        $fileName = 'auth_logs_' . time() . '.xlsx';

        $history = DataHistory::createDataHistory(
            DataHistoryEnum::EXPORT_AUTHENTICATION_LOG_HISTORY->value,
            DataHistoryEnum::EXPORT->value,
            $fileName,
            $filters
        );

        ExportAuthenticationLogsOpenSpout::dispatch($filters, $columns, auth()->user(), $fileName, $history->ref);

        return response()->json([
            'status' => true,
            'message' => 'Your file is being prepared. You will be notified once it is ready.',
        ]);
    }


    private function filterDefaultOnPayment(
        Collection $collection,
        string $search,
        int $perPage
    ): LengthAwarePaginator {
        $page = (int) request()->input('page', 1);
        $searchLower = \Str::lower($search);

        $filtered = $collection->filter(function ($claim) use ($searchLower) {
            $searchableFields = [
                $claim->assignee?->toUser->name ?? '',
                __('claim.status.' . $claim->status),
                __('claim.type.' . $claim->type),
                (string) ($claim->amount ?? ''),
                $claim->claim_number ?? '',
                Carbon::parse($claim->accident_date)->format('d-m-Y'),
                Carbon::parse($claim->intimation_date)->format('d-m-Y'),
                Carbon::parse($claim->created_at)->format('d-m-Y'),
            ];

            return collect($searchableFields)->some(
                fn($field) => \Str::contains(\Str::lower($field), $searchLower)
            );
        });

        $page = max(1, $page);

        $items = $filtered->forPage($page, $perPage);

        return new LengthAwarePaginator(
            $items->values(),
            $filtered->count(),
            $perPage,
            $page,
            [
                'path' => url()->current(),
                'query' => request()->query(),
            ]
        );
    }

    private function filterRejectedClaims(
        Collection $collection,
        string $search,
        int $perPage
    ): LengthAwarePaginator {
        $page = (int) request()->input('page', 1);
        $searchLower = \Str::lower($search);

        $filtered = $collection->filter(function ($rejectedClaim) use ($searchLower) {
            $searchableFields = [
                $rejectedClaim->claim?->claim_number ?? '',
                $rejectedClaim->reason ?? '',
                __('report.' . str_replace(' ', '_', $rejectedClaim->status)),
            ];

            return collect($searchableFields)->some(
                fn($field) => \Str::contains(\Str::lower($field), $searchLower)
            );
        });

        $page = max(1, $page);

        $items = $filtered->forPage($page, $perPage);

        return new LengthAwarePaginator(
            $items->values(),
            $filtered->count(),
            $perPage,
            $page,
            [
                'path' => url()->current(),
                'query' => request()->query(),
            ]
        );
    }



    private function isPrivilegedUser(User $user)
    {
        return in_array($user->role_id, [Roles::SUPER_ADMIN->value, Roles::SUPERVISOR->value]);
    }


    public function getMessages(ExportMessagesRequest $request)
    {
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $user = auth()->user();

        // Fix permissions handling
        $permissions = safe_json_decode($user->permission->first()->reports ?? '{}');
        $showReport = !empty($permissions->show_reports) && $permissions->show_reports == 1;

        $query = Message::filter();

        if (!$showReport) {
            $claimIds = $this->getUserClaimIds($user);

            if (!empty($claimIds)) {
                $query->whereIn('claim_id', $claimIds);
            } else {
                // prevent returning all data لو مفيش claims
                $query->whereRaw('1 = 0');
            }
        }

        $messages = $query->paginate($perPage, ['*'], 'page', $page);

        // Transform links
        $messages->getCollection()->transform(function ($item) {
            $item->links = json_decode($item->links);
            return $item;
        });

        return response()->json([
            'success' => $messages->isNotEmpty(),
            'message' => $messages->isEmpty()
                ? 'No Messages found.'
                : 'Messages data retrieved successfully.',
            'data' => $messages->items(),
            'meta' => [
                'total' => $messages->total(),
                'per_page' => $messages->perPage(),
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'from' => $messages->firstItem(),
                'to' => $messages->lastItem(),
            ],
        ]);
    }

    private function getUserClaimIds($user): array
    {
        if ($user->role_id === Roles::SENIOR_COLLECTOR->value) {
            $subordinateIds = $user->subordinates()->pluck('id')->toArray();
            $userIds = array_merge([$user->id], $subordinateIds);

            return Assigning::whereIn('to', $userIds)
                ->where('is_active', 1)
                ->pluck('claim_id')
                ->toArray();
        }

        return Assigning::where('to', $user->id)
            ->where('is_active', 1)
            ->pluck('claim_id')
            ->toArray();
    }

    public function exportMessages(Request $request)
    {
        $fileName = 'messages_export_' . time() . '.xlsx';

        Excel::queue(new MessageExport(), 'exports/' . $fileName, 'public')->chain([
            new \App\Jobs\MarkExportAsReady($fileName, auth()->user(), ExportType::EXPORT_MESSAGES),
        ]);
        return response()->json([
            'status' => true,
            'message' => 'Your file is being prepared. You will be notified once it is ready.',
        ]);
    }

    public function OpenSpoutexportMessages(ExportMessagesRequest $request)
    {
        $fileName = 'messages_export_' . time() . '.xlsx';
        
        $filters = $request->filters();
        $history = DataHistory::createDataHistory(DataHistoryEnum::EXPORT_MESSAGES->value, DataHistoryEnum::EXPORT->value, '',$filters);
        \App\Jobs\ExportMessagesOpenSpout::dispatch(auth()->user(), $fileName, $history->ref,$request->columns() ?? [],$filters);

        return $this->successResponse('Your file is being prepared. You will be notified once it is ready.');
    }


    public function getDataHistory(ExportDataHistoryRequest $request)
    {
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $user = auth()->user();

        $query = DataHistory::filter()->latest();

        


        $data = $query->paginate($perPage, ['*'], 'page', $page);



        return response()->json([
            'success' => $data->isNotEmpty(),
            'message' => $data->isEmpty()
                ? 'No data found.'
                : 'data data retrieved successfully.',
            'data' => $data->items(),
            'meta' => [
                'total' => $data->total(),
                'per_page' => $data->perPage(),
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
        ]);
    }


    public function OpenSpoutExportDataHistory(ExportDataHistoryRequest $request)
    {
        $fileName = 'export_import_log_' . time() . '.xlsx';
        $filters = $request->except('columns');
        $history = DataHistory::createDataHistory(DataHistoryEnum::EXPORT_IMPORT_HISTORIES->value, DataHistoryEnum::EXPORT->value, '', $filters);
        \App\Jobs\ExportImportDataOpenSpout::dispatch(auth()->user(), $fileName, $history->ref, $filters, $request->columns ?? []);

        return response()->json([
            'status' => true,
            'message' => 'Your file is being prepared. You will be notified once it is ready.',
        ]);
    }

    // public function cancellProcessing($id)
    // {
    //     $dataHistory = DataHistory::findOrFail($id);

    //     if ($dataHistory->status !== DataHistoryEnum::PROCESSING->value) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Only files that are currently processing can be cancelled.',
    //         ], 400);
    //     }

    //     $dataHistory->status = DataHistoryEnum::CANCELLED->value;

    //     if ($dataHistory->type === DataHistoryEnum::IMPORT->value && $dataHistory->title === DataHistoryEnum::ACCOUNT_REPORT->value) {

    //         $cacheKey = "update_billed:{$dataHistory->ref}:processed_ids";

    //         $ids = Cache::get($cacheKey, []);
    //         if (!empty($ids)) {

    //             Claim::whereIn('id', $ids)
    //                 ->where('is_billed', true)
    //                 ->update([
    //                     'is_billed'   => false,
    //                     'invoice_date' => null,
    //                 ]);
    //             Claim::whereIn('id', $ids)
    //                 ->where('is_billed', false)
    //                 ->update([
    //                     'is_billed'   => true,
    //                     'invoice_date' => Carbon::now(),
    //                 ]);
    //         }
    //         Cache::forget($cacheKey); // Clear the cache key  

    //     }

    //     $dataHistory->save();

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'The processing of the file has been cancelled successfully.',
    //     ]);
    // }

    public function cancellProcessing(int $id): JsonResponse
    {
        $dataHistory = DataHistory::findOrFail($id);

        if (!$dataHistory->isProcessing()) {
            return $this->apiErrorResponse('Only files that are currently processing can be cancelled.');
        }

        DB::transaction(function () use ($dataHistory) {
            $dataHistory->markAsCancelled();

            if ($dataHistory->isAccountReportImport()) {
                (new RevertBilledClaimsAction($dataHistory->getCacheKey()))->execute();
            }

            if ($dataHistory->isBacklogImport()) {
                (new RevertImportedBacklogClaimsAction($dataHistory->getImportCacheKey()))->execute();
            }

            if ($dataHistory->isAssignedClaims()) {
                (new RevertAssignClaimsAction($dataHistory->getBulkAssignCacheKey()))->execute();
            }
        });

        return $this->successResponse('The processing of the file has been cancelled successfully.');
    }



    public function cliamsAssignedHistory(ExportClaimsAssignedRequest $request)
    {
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $user = auth()->user();

        // Fix permissions handling
        $permissions = safe_json_decode($user->permission->first()->reports ?? '{}');
        $showReport = !empty($permissions->show_reports) && $permissions->show_reports == 1;

        $query = Assigning::filter($request->filters());

        if (!$showReport) {
            $claimIds = $this->getUserClaimIds($user);

            if (!empty($claimIds)) {
                $query->whereIn('claim_id', $claimIds);
            } else {
                // prevent returning all data لو مفيش claims
                $query->whereRaw('1 = 0');
            }
        }

        $data = $query->latest()->paginate($perPage, ['*'], 'page', $page);

         return response()->json(
            new CollectionResource($data, 'Claim Assigned Users information', AssignedHistoryResource::class,[true])
        );
    }


    public function OpenSpoutExportCliamsAssignedHistory(ExportClaimsAssignedRequest $request)
    {
        $fileName = 'assigned_claims_history_export_' . time() . '.xlsx';
        
        $filters = $request->filters();
        $history = DataHistory::createDataHistory(DataHistoryEnum::EXPORT_ASSIGNED_CLAIMS_DATA->value, DataHistoryEnum::EXPORT->value, '',$filters);
        \App\Jobs\ExportAssignedClaimsOpenSpout::dispatch(auth()->user(), $fileName, $history->ref,$request->columns() ?? [],$filters);

        return $this->successResponse('Your file is being prepared. You will be notified once it is ready.');
    }
}
