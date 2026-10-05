<?php

namespace App\Events\ClaimInfo;

use App\Http\Resources\Api\V1\RejectionResource;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UpdateClaimRequestEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $claimId;

    public function __construct($claimId)
    {
        $this->claimId       = $claimId;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('claim-requests.' . $this->claimId),
        ];
    }

    public function broadcastWith(): array
    {
         return [
        'id' => $this->claimId,
            'event'=>'running',
        ];
    }
}
