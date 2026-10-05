<?php

namespace App\Http\Resources\Api\V1\Target;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TargetResource extends JsonResource
{
    protected $histories;

    public function __construct($resource, $histories = false)
    {
        parent::__construct($resource);
        $this->histories = $histories;
    }
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $response= [
            'id' => $this->currentTarget?->id,
            'target_type'=> $this->currentTarget?->target_type,
            'current' => [
                'user'       =>[
                    'id' => $this->currentTarget?->user_id,
                    'name' => $this->currentTarget?->user?->name,
                    'role' => $this->currentTarget?->user?->role?->title
                ],
                'target'        => $this->currentTarget?->target,
                'achieved'      => $this->currentTarget?->achieved,
                'remaining'     => $this->currentTarget?->remaining,
                'is_completed'  => $this->currentTarget?->is_completed,
                'start_date'    => $this->currentTarget?->start_date,
                'end_date'      => $this->currentTarget?->end_date,
                'status'        => $this->currentTarget?->status,
                'percentage'    => $this->currentTarget?->percentage,
            ],
            'previous' => [
                 'user'       =>[
                    'id' => $this->previousTarget?->user_id,
                    'name' => $this->previousTarget?->user?->name,
                    'role' => $this->previousTarget?->user?->role?->title
                ],
                'target'        => $this->previousTarget?->target,
                'achieved'      => $this->previousTarget?->achieved,
                'remaining'     => $this->previousTarget?->remaining,
                'is_completed'  => $this->previousTarget?->is_completed,
                'start_date'    => $this->previousTarget?->start_date,
                'end_date'      => $this->previousTarget?->end_date,
                'status'        => $this->previousTarget?->status,
                'percentage'    => $this->previousTarget?->percentage,
            ],
        ];
        if ($this->histories === true) {
            $response['histories'] = $this->currentTarget?->histories;
        }

        return $response;
    }
}
