<?php

namespace App\Http\Controllers\Api\V1\Deleted;

use App\Http\Requests\V1\CreateRejectionRequest;
use App\Http\Requests\V1\StoreRejectionsFilesRequest;
use App\Http\Resources\Api\V1\Collections\Collection;
use App\Http\Resources\Api\V1\RejectionResource;
use App\Models\RejectedClaim;
use App\Services\RejectionService;
use Exception;
use Illuminate\Routing\Controller;

class RejectedClaimController extends Controller
{
    public function __construct(public RejectionService $service
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        return response()->json(

            new Collection($this->service->list(), $message = 'get all rejection succssfully', $resourceClass = RejectionResource::class)
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateRejectionRequest $request)
    {
        //
        try {

            return response()->json([
                'error' => false,
                'message' => 'created succssfully',
                'data' => RejectionResource::make($this->service->create($request->validated())),
            ]);

        } catch (Exception $exception) {
            return response()->json([
                'error' => true,
                'message' => $exception->getMessage(),
            ]);
        }
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
    public function show(RejectedClaim $rejectedClaim)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(RejectedClaim $rejectedClaim)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update($request, RejectedClaim $rejectedClaim)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RejectedClaim $rejectedClaim)
    {
        //
    }

    public function accept($rejection)
    {
        try {

            $this->service->acceptRejection($rejection);

            return response()->json([
                'error' => false,
                'message' => 'rejection accepted successfully ',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => true,
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
                'error' => false,
                'message' => 'delegation rejected successfully ',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ]);

            // throw new NotExistsException('Claim does not exists');
        }

        // return DelegationResource::make($this->service->acceptDelegation($delegation));
    }
}
