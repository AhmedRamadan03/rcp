<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DataHistoryEnum;
use App\Exports\DelegationsExport;
use App\Http\Requests\Request;
use App\Http\Requests\V1\Delegations\CreateRequests\DelegationStoreRequest;
use App\Http\Requests\V1\Delegations\DelegationFilterRequest;
use App\Http\Resources\Api\V1\Collections\Collection;
use App\Http\Resources\Api\V1\DelegationResource;
use App\Jobs\ExportDelegationsOpenSpout;
use App\Models\DataHistory;
use App\Models\Delegation;
use App\Services\DelegationService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Routing\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Http\JsonResponse;


class DelegationController extends Controller
{
    public function __construct(private DelegationService $delegationService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(DelegationFilterRequest $request)
    {
        return response()->json(
            new Collection($this->delegationService->list($request->filters(), $request->perPage()), $message = 'Delegations returned successfully', $resourceClass = DelegationResource::class)
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(DelegationStoreRequest $request)
    {
        //
        try {
            $delegation = $this->delegationService->createDelegation($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Delegation created successfully',
                'data' => DelegationResource::make($delegation),
            ], 201);

        } catch (Exception $exception) {
            return response()->json([
                'error' => true,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }


    public function cancel(Delegation $delegation)
    {
        try {
            $this->delegationService->cancelDelegation($delegation->id);

            return response()->json([
                'success' => true,
                'message' => 'Delegation cancelled successfully'
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Delegation Not Found to be Canceld'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function export(DelegationFilterRequest $request): JsonResponse
    {
        $filters = $request->filters();
        $columns = $request->columns();

        $fileName = 'delegations_' . time() . '.xlsx';

        $history = DataHistory::createDataHistory(
            DataHistoryEnum::EXPORT_DELEGATION_HISTORY->value,
            DataHistoryEnum::EXPORT->value,
            $fileName,
            $filters
        );

        ExportDelegationsOpenSpout::dispatch($filters, $columns, auth()->user(), $fileName, $history->ref);

        return response()->json([
            'status' => true,
            'message' => 'Your file is being prepared. You will be notified once it is ready.',
        ]);
    }
}
