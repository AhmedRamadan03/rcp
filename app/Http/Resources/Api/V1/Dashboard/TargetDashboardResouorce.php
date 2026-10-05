<?php

namespace App\Http\Resources\Api\V1\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TargetDashboardResouorce extends JsonResource
{

  
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
              "id" => $this->user_id,
              "name" => $this->user?->name,
              'target'=>$this->target,
              'achieved'=>$this->achieved,
              'percentage'    => $this->percentage,
            ];
    }
}
