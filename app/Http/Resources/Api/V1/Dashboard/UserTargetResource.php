<?php

namespace App\Http\Resources\Api\V1\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserTargetResource extends JsonResource
{


    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
              'target'=>$this->currentTarget?->target,
              'achieved'=>$this->currentTarget?->achieved,
              'remaining'=>$this->currentTarget?->remaining,
              'percentage'    => $this->currentTarget?->percentage,
            ];
    }
}
