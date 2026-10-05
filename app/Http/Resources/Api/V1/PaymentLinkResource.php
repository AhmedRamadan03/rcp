<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentLinkResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->send_status,
            'amount' => $this->amount,
            'link' => url('/api/bit.ly/'.$this->link),
            'views_count' => $this->views_count,
            'last_access' => $this->last_access,
            'transactions' => PaymentTransactionResource::collection($this->transactions),
        ];
    }
}
