<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DataHistoryEnum;
use App\Http\Requests\V1\RejectedClaims\RejectedClaimFilterRequest;
use App\Http\Requests\V1\StoreRejectionsFilesRequest;
use App\Http\Resources\Api\V1\Collections\Collection;
use App\Http\Resources\Api\V1\RejectionResource;
use App\Jobs\ExportClientRequestsOpenSpout;
use App\Models\DataHistory;
use App\Models\RejectedClaim;
use App\Services\RejectionService;
use Exception;
use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;

class RejectedClaimController extends Controller
{
    public function __construct(public RejectionService $service
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(RejectedClaimFilterRequest $request)
    {
        
        $filters = $request->filters();
        
        return response()->json(
            new Collection(
                $this->service->list($filters, request()->per_page),
                'get all rejection succssfully',
                RejectionResource::class
            )
        );
    }


    public function storeFiles(StoreRejectionsFilesRequest $request)
    {

        $this->service->uploadRejectionFiles($request->validated());

        return response()->json([
            'error' => false,
            'message' => 'files uploaded successfully',
        ]);

    }

    /**
     * Display the specified resource.
     */
    public function show($rejectedClaim)
    {
        $rejectedClaim = RejectedClaim::with('attachments')->find($rejectedClaim);
        if (!$rejectedClaim) {
            return response()->json([
                'success' => false,
                'message' => 'Client Request not found',
            ]);
        }
         return response()->json([
            'success' => true,
            'message' => 'Claim successfully shown.',
            'data' => new RejectionResource($rejectedClaim,1),
        ]);
    }



    public function accept($rejection)
    {
        try {

            $this->service->acceptRejection($rejection);

            return response()->json([
                'success' => true,
                'message' => 'rejection accepted successfully ',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);

            // throw new NotExistsException('Claim does not exists');
        }

        // return DelegationResource::make($this->service->acceptDelegation($delegation));
    }

    public function reject($rejection)
    {
        try {

            $this->service->rejectRejection($rejection);

            return response()->json([
                'success' => true,
                'message' => 'delegation rejected successfully ',
            ]);
        } catch (Exception $e) {
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);

            // throw new NotExistsException('Claim does not exists');
        }

        // return DelegationResource::make($this->service->acceptDelegation($delegation));
    }


    /**
     * Export requests to Excel
     */
    public function export(RejectedClaimFilterRequest $request): JsonResponse
    {
        $filters = $request->filters();
        $columns = $request->columns();

        $fileName = 'client_requests_' . time() . '.xlsx';

        $history = DataHistory::createDataHistory(
            DataHistoryEnum::EXPORT_REJECTED_CLAIMS->value,
            DataHistoryEnum::EXPORT->value,
            $fileName,
            $filters ?? []
        );

        ExportClientRequestsOpenSpout::dispatch($filters, $columns, auth()->user(), $fileName, $history->ref);

        return response()->json([
            'status' => true,
            'message' => 'Your file is being prepared. You will be notified once it is ready.',
        ]);
    }



}
