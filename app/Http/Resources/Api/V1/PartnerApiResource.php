<?php

namespace App\Http\Resources\Api\V1;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartnerApiResource  extends JsonResource
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
            'title' => $this->title,
            'api_client_id' => $this->api_client_id,
            'client'=> $this->client?->title,
            'status' => $this->status,
            'created_by' => $this->createdBy?->name,
            'revoked_by' => $this->revokedBy?->name,
            'created_at' => Carbon::parse($this->created_at)->format('Y-m-d H:i:s'),
            'revoked_at' => $this->revoked_at ? Carbon::parse($this->revoked_at)->format('Y-m-d H:i:s') : null,
        ];
    }
}