<?php

namespace App\Http\Resources\Api\V1\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageTemplateResource extends JsonResource
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
            "title" => $this->title,
            "model" => $this->model,
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at,
            "custom_templates" => CustomTemplateResource::collection($this->customTemplates),
            "variables"=> TemplateVariableResource::collection($this->variables)
        ];
    }
}