<?php

namespace App\Http\Resources\Api\V1\Reports;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class AuthenticationLogReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {

        return [
            'id'=> $this->id,
            'user' => $this->whenLoaded('authenticatable', function () {
                return [
                    'user_id' => $this->authenticatable->id,
                    'name' => $this->authenticatable->name,
                    'email' => $this->authenticatable->email,
                ];
            }),
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'login_at' => $this->login_at ? Carbon::parse($this->login_at)->format('Y-m-d H:i:s') : null,
            'login_successful' => $this->login_successful,
            'logout_at' => $this->logout_at ? Carbon::parse($this->logout_at)->format('Y-m-d H:i:s') : null,
        ];
    }
}
