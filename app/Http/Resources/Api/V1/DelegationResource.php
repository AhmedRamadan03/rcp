<?php

namespace App\Http\Resources\Api\V1;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class DelegationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {

        $startDate = Carbon::parse($this->start_date);
        $durationDays = null;

        if ($this->end_date) {
            $endDate = Carbon::parse($this->end_date);
            $durationDays = $startDate->diffInDays($endDate);
        } else if ($this->is_active) {
            // Calculate days from start until now for active delegations without end date
            $durationDays = $startDate->diffInDays(now());
        }

        return [
            // 'comment' => $this->delegation_comment,
            // 'expiration_date' => $this->delegation_date_exp,
            // 'from' => $this->fromUser?->name,
            // 'to' => $this->toUser->name,
            // 'supervisor' => $this->supervisorUser?->name,
            // 'status' => $this->status,
            'id' => $this->id,
            'is_active' => $this->is_active,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'duration_days' => $durationDays,
            'status' => $this->status,
            'from_user' => $this->fromUserWithTrashed ? [
                'id' => $this->fromUserWithTrashed->id,
                'name' => $this->fromUserWithTrashed->name,
                'email' => $this->fromUserWithTrashed->email,
                'role' => $this->fromUserWithTrashed->role?->title ?? '',
                'deactivated_user' => $this->fromUserWithTrashed->trashed(),
            ] : null,
            'to_user' => $this->toUserWithTrashed ? [
                'id' => $this->toUserWithTrashed->id,
                'name' => $this->toUserWithTrashed->name,
                'email' => $this->toUserWithTrashed->email,
                'role' => $this->toUserWithTrashed->role?->title ?? '',
                'deactivated_user' => $this->toUserWithTrashed->trashed(),
            ] : null,
            'claims_status_before_delegation' => json_decode($this->claims_status_before_delegation, true),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

        ];
    }
}
