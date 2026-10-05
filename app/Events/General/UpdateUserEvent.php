<?php

namespace App\Events\General;

use App\Http\Resources\Api\V1\RejectionResource;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UpdateUserEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;


    public function __construct()
    {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('update-user'),
        ];
    }

    public function broadcastWith(): array
    {
         return [
            'event'=>'running',
        ];
    }
}
