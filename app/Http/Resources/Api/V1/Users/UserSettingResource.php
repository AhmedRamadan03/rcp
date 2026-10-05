<?php

namespace App\Http\Resources\Api\V1\Users;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserSettingResource extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->userSettings->mapWithKeys(fn($setting) => [
            $setting->key => $setting->value,
        ])->toArray();
    }
}
