<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'paid_amount' => $this->paid_amount,
            'remaining_amount' => $this->amount - $this->paid_amount,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'due_date' => $this->due_date,
            'links' => PaymentLinkResource::collection($this->links),
        ];
    }
}
