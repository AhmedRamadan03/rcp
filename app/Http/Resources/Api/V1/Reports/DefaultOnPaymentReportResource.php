<?php

namespace App\Http\Resources\Api\V1\Reports;

use Illuminate\Http\Resources\Json\JsonResource;

class DefaultOnPaymentReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {

        return [
            "id"=> $this->id,
            'assigned_to' => $this->assignee?->toUser?->name ?? null,
            'claim_number' => $this->claim_number,
            'claim_status' => $this->status,
            'claim_amount' => $this->amount,
            'accident_date' => $this->accident_date,
            'intimation_date' => $this->intimation_date,
            'insertion_date' => $this->created_at,
        ];
    }
}
