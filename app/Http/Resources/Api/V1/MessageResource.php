<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'phone_number' => $this->phone_number,
            'send_date' => $this->send_date,
            'status' => $this->status,
            'status_message' => $this->status_message,
            'message_status' => $this->message_status,
            'is_elm_message' => $this->is_elm_message,
            'national_id' => $this->national_id ?? null,
            'response' => $this->response ?? null,
        ];
    }
}
