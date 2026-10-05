<?php

namespace App\Http\Resources\Api\V1\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientInfoResource extends JsonResource
{


    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => config('app.client_name'),
            'logo' => getCompanyLogo(),
        ];
    }
}
