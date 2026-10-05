<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssigningResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (!$this->resource || !$this->toUser) {
            return [];
        }

        return [
            'to_user'     => $this->userShape($this->toUser),
            'from_user'   => $this->userShape($this->fromUser),      // null on first creation — handled cleanly
            'assigned_by' => $this->userShape($this->supervisorUser),
            'assign_date' => $this->assign_date,
        ];
    }

    private function userShape(?object $user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->name,'user_type' => $user->user_type] : null;
    }
}