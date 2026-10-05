<?php

namespace App\Http\Resources\Api\V1\Reports;

use Illuminate\Http\Resources\Json\JsonResource;

class CollectorTargetsReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {

        return [
            "target_id" => $this->id,
            "target" => $this->target,
            'achieved' => $this->achieved, 
            "remaining" => $this->remaining,
            "start_date" => $this->start_date,
            "end_date" => $this->end_date,
            "status" => $this->deleted_at ? "Cancelled" : $this->status
        ];
    }
}
