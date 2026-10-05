<?php

namespace App\Http\Resources\Api\V1\Reports;

use Illuminate\Http\Resources\Json\JsonResource;

class CollectorsTargetsReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {

        return [
            "user_id" => $this->id,
            "name" => $this->name,
            "role" => $this->role->title ?? '',
            "total_claims" => $this->all_claims ?? 0,
            "target_achieved" => $this->target_achieved ?? 0,
            "target_remaining" => $this->target_remaining ?? 0,
            "total_target" => $this->target_sum ?? 0,
            "status" => ($this->active_targets_count ?? 0) > 0 ? "active" : "inactive",
        ];
    }
}
