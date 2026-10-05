<?php

namespace App\Http\Resources\Api\V1\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomTemplateResource extends JsonResource
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
            "third_party_id" => $this->third_party_id ?? null,
            'name' => $this->name,
            "provider_template_code" => $this->provider_template_code,
            "body" => $this->body,
            "provider_configs" => $this->whenLoaded('providerConfigs', function () {
                return $this->providerConfigs->map(fn ($config) => [
                    'template_variable_id' => $config->template_variable_id,
                    'variable_key' => $config->templateVariable?->key,
                    'provider_key' => $config->provider_key,
                ]);
            }),
            "is_active" => (int) $this->is_active,
            "client_id" => $this->client_id,
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at,
            'available_variables' => TemplateVariableResource::collection(
                $this->whenLoaded('messageTemplate', function () {
                    return $this->messageTemplate->variables ?? collect();
                })
            ),
        ];
    }
}
