<?php

namespace App\Http\Controllers\Api\V1\Deleted;

use App\Http\Requests\V1\CreateMessageRequest;
use App\Http\Resources\Api\V1\Collections\Collection;
use App\Http\Resources\Api\V1\MessageResource;
use App\Models\Message;
use App\Services\MessageService;
use Exception;
use Illuminate\Routing\Controller;

class MessageController extends Controller
{
    public function __construct(public MessageService $service) {}

    /**
     * Display a listing of the resource.
     */
    public function index($claim_id)
    {
        //
        // return $this->service->list($claim_id);
        return response()->json(
            new Collection($this->service->list($claim_id), $message = 'list of messages', $resourceClass = MessageResource::class)
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
    public function store(CreateMessageRequest $request)
    {
        //
        try {
            // return ($this->service->create($validated_request));
            return response()->json([
                'error' => false,
                'message' => 'delegation created succssfully',
                'data' => MessageResource::make($this->service->create($request)),
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
    public function show(Message $message)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Message $message)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update($request, Message $message)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Message $message)
    {
        //
    }
}
