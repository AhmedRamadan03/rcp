<?php

namespace App\Http\Controllers\Api\V1\Deleted;

use App\Exceptions\NotExistsException;
use App\Http\Requests\V1\CreateAssigningRequest;
use App\Http\Resources\Api\V1\AssigningResource;
use App\Http\Resources\Api\V1\Collections\Collection;
use App\Models\Assigning;
use App\Services\AssigningService;
use Illuminate\Routing\Controller;

class AssigningController extends Controller
{
    public function __construct(public AssigningService $service) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        return response()->json(new Collection($this->service->list(), $message = 'get all Assignings succssfully', $resourceClass = AssigningResource::class));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function store(CreateAssigningRequest $request)//
    {
        //
        try {
            $validated_request = [
                'claims_id' => $request->validated('claims_id'),
                'supervisor_id' => $request->validated('supervisor_id'),
                'to' => $request->validated('assigns_to'),
                'from' => $request->get('from') ?? null,
            ];

            // return $this->service->create($validated_request);

            $this->service->create($validated_request);

            return response()->json([
                'error' => false,
                'message' => 'assigned successfully',
            ]);
        } catch (NotExistsException $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Assigning $assigning)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Assigning $assigning)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update($request, Assigning $assigning)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Assigning $assigning)
    {
        //
    }
}
