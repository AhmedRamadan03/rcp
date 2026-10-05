<?php

namespace App\Http\Resources\Api\V1\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemplateVariableResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "template_id" => $this->template_id,
            "key" => $this->key,
            "value" => $this->value,
            "label" => $this->label,
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at,
        ];
    }
}