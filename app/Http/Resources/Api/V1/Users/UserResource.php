<?php

namespace App\Http\Resources\Api\V1\Users;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "name" => $this->name,
            "email" => $this->email,
            "role" => $this->role->title ?? '',
            "role_id" => $this->role_id,
            "user_type" => $this->user_type,
            "supervisor" => $this->supervisor?->name,
        ];
    }
}
