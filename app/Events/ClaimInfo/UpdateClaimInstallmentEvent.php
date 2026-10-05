<?php

namespace App\Events\ClaimInfo;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UpdateClaimInstallmentEvent implements ShouldBroadcast
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
            new Channel('claim-installemts.' . $this->claimId),
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
