<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DataHistoryEnum;
use App\Exceptions\RequiredSupervisorApprovalException;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StatusChangeRequests\StatusChangeRequestFilterRequest;
use App\Jobs\ExportPermissionRequestsOpenSpout;
use App\Models\DataHistory;
use App\Services\RequestsService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RequestController extends Controller
{
    private RequestsService $service;

    public function __construct(RequestsService $service)
    {
        $this->service = $service;
    }

    /**
     * Get list of requests with pagination
     */
    public function index(StatusChangeRequestFilterRequest $request): JsonResponse
    {
        try {

            $serviceList = $this->service->list($request->filters(), $request->perPage() ?? 10);

            return response()->json([
                'success' => true,
                'message' => 'Requests retrieved successfully',
                'data' => [
                    'requests' => $serviceList->items(),
                    'pagination' => [
                        'current_page' => $serviceList->currentPage(),
                        'last_page' => $serviceList->lastPage(),
                        'per_page' => $serviceList->perPage(),
                        'total' => $serviceList->total(),
                        'next_page_url' => $serviceList->nextPageUrl(),
                        'prev_page_url' => $serviceList->previousPageUrl()
                    ]
                ]
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),

            ], 422);
        }
    }

    /**
     * Reject a request
     */
    public function show(int $requestId): JsonResponse
    {
        try {


            $resource = $this->service->show($requestId);

            return $this->successResponse('Request retrieved successfully', $resource->toArray('show'));
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'errors' => $exception->validator->errors(),
                'message' => $exception->getMessage(),
                'data' => []
            ]);

        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => []
            ]);
        }
    }
    public function reject(Request $request, int $requestId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reject_reason' => 'required|string'
            ]);

            $this->service->reject($requestId, $validated);

            return response()->json([
                'success' => true,
                'message' => __('request.request_has_been_rejected_successfully'),
                'data' => null
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'errors' => $exception->validator->errors(),
                'message' => $exception->getMessage(),
                'data' => []
            ],422);

        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => []
            ],400);
        }
    }

    /**
     * Cancel a request
     */
    public function cancel(Request $request, int $requestId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'cancel_reason' => 'nullable|string'
            ]);
            $this->service->cancel($requestId, $validated);

            return response()->json([
                'success' => true,
                'message' => __('request.request_has_been_canceled_successfully'),
            ]);

        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'errors' => $exception->validator->errors(),
                'message' => $exception->getMessage(),
            ],422);

        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ],400);
        }
    }

    /**
     * Accept a request
     */
    public function accept(int $requestId): JsonResponse
    {
        try {
            $this->service->accept($requestId);

            return response()->json([
                'success' => true,
                'message' => __('request.request_has_been_accepted_successfully'),
                'data' => null
            ]);
        } catch (RequiredSupervisorApprovalException $e) {
            return response()->json([
                'success' => true,
                'message' => $e->getMessage(),
                'data' => null
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => null
            ], 400);
        }
    }

    /**
     * Escalate a request
     */
    public function escalate(int $requestId): JsonResponse
    {
        try {
            $this->service->escalate($requestId);

            return response()->json([
                'success' => true,
                'message' => __('request.request_has_been_escalated_successfully'),
                'data' => null
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => null
            ], 400);
        }
    }

    /**
     * Export requests to Excel
     */
    public function export(StatusChangeRequestFilterRequest $request): JsonResponse
    {
        $filters = $request->filters();
        $columns = $request->columns();
        $fileName = 'requests_' . time() . '.xlsx';

        $history = DataHistory::createDataHistory(
            DataHistoryEnum::EXPORT_PERMISSION_REQUESTS->value,
            DataHistoryEnum::EXPORT->value,
            $fileName,
            $filters ?? []
        );

        ExportPermissionRequestsOpenSpout::dispatch($filters, $columns, auth()->user(), $fileName, $history->ref);

        return response()->json([
            'status' => true,
            'message' => 'Your file is being prepared. You will be notified once it is ready.',
        ]);
    }

}
