<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserCurrentTargetResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'collector_name' => $this->user->name,
            'total_target' => $this->target,
            'total_collected' => $this->achieved,
            'total_remaining' => $this->remaining,
            'target_percentage' => $this->percentage,
            'target_progress' => $this->width_percentage,
        ];
    }
}
