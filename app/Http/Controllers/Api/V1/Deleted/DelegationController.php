<?php

namespace App\Http\Controllers\Api\V1\Deleted;

use App\Http\Requests\V1\CreateDelegationRequest;
use App\Http\Requests\V1\RejectDelegationRequest;
use App\Http\Resources\Api\V1\Collections\Collection;
use App\Http\Resources\Api\V1\DelegationResource;
use App\Models\Delegation;
use App\Services\DelegationService;
use Exception;
use Illuminate\Routing\Controller;

class DelegationController extends Controller
{
    public function __construct(public DelegationService $service) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(
            new Collection($this->service->list(), $message = 'returned successfully', $resourceClass = DelegationResource::class)
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
    public function store(CreateDelegationRequest $request)
    {
        //
        try {
            $validated_request = [
                'claim_id' => $request->validated('claim_id'),
                'supervisor_id' => $request->validated('supervisor_id'),
                'to' => $request->validated('delegate_to'),
                'from' => $request->get('from') ?? null,
                'delegation_comment' => $request->get('comment') ?? null,
                'delegation_date_exp' => $request->get('expiration_date') ?? null,
            ];

            // return ($this->service->create($validated_request));
            return response()->json([
                'error' => false,
                'message' => 'delegation created succssfully',
                'data' => DelegationResource::make($this->service->create($validated_request)),
            ]);

        } catch (Exception $exception) {
            return response()->json([
                'error' => true,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Delegation $delegation)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Delegation $delegation) {}

    /**
     * Update the specified resource in storage.
     */
    public function update($request, Delegation $delegation) {}

    public function destroy(Delegation $delegation)
    {
        //
    }

    public function accept($delegation)
    {
        try {

            Delegation::findOrFail($delegation);

            $this->service->acceptDelegation($delegation);

            return response()->json([
                'error' => false,
                'message' => 'delegation accepted successfully ',
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

    public function reject(RejectDelegationRequest $request)
    {
        try {

            $this->service->rejectDelegation($request->validated());

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
