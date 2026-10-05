<?php

namespace App\Http\Requests\V1\Settings\UpdateRequests;

use App\Enums\ThirdPartyType;
use App\Models\ThirdParty;
use App\Models\CustomTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CustomTemplateUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $templateId = CustomTemplate::find($this->custom_template_id)?->template_id;

        return [
            'name' => [
                'required',
                'string',
                Rule::unique('custom_templates', 'name')
                    ->ignore($this->custom_template_id)
                    ->when($templateId !== null, fn ($rule) => $rule->where(
                        fn ($query) => $query->where('template_id', $templateId)
                    )),
            ],
            'body' => 'required|string',
            'custom_template_id' => 'required|exists:custom_templates,id',

            'third_party_id' => [
                'required',
                Rule::exists('third_parties', 'id')
                    ->where(function ($query) {
                        $query->where('client_id', $this->user()->client_id)
                            ->where('type', ThirdPartyType::SMS->value);
                    }),
            ],

            'provider_template_code' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => filled($this->provider_configs)),
            ],

            'provider_configs' => [
                'nullable',
                'array',
                'min:1',
                Rule::requiredIf(function () {
                    if (!$this->third_party_id) {
                        return false;
                    }

                    return ThirdParty::find($this->third_party_id)?->isApiProvider() ?? false;
                }),
            ],
            'provider_configs.*.template_variable_id' => [
                'required_with:provider_configs',
                'integer',
                'distinct',
                Rule::exists('template_variables', 'id')
                    ->when($templateId !== null, fn ($rule) => $rule->where(
                        fn ($query) => $query->where('template_id', $templateId)
                    )),
            ],

            'provider_configs.*.provider_key' => 'required_with:provider_configs|string|max:255',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (empty($this->provider_configs) || !$this->third_party_id) {
                return;
            }

            if ($validator->errors()->has('third_party_id')) {
                return;
            }

            $thirdParty = ThirdParty::find($this->third_party_id);

            if (!$thirdParty?->isApiProvider()) {
                $validator->errors()->add(
                    'third_party_id',
                    'The selected provider must be configured as an API provider to map provider variables.'
                );
            }
        });
    }


    public function messages(): array
    {
        return [
            'name.required' => 'The custom template name is required.',
            'name.string' => 'The custom template name must be a string.',
            'name.unique' => 'The custom template name must be unique.',

            'body.required' => 'The custom template body is required.',
            'body.string' => 'The custom template body must be a string.',

            'custom_template_id.required' => 'The custom template ID is required.',
            'custom_template_id.exists' => 'The selected custom template does not exist.',

            'third_party_id.required' => 'A provider must be selected.',
            'third_party_id.exists' => 'The selected provider is not a valid active SMS provider for this client.',

            'provider_template_code.required' => 'The provider template code is required when mapping provider variables.',
            'provider_template_code.string' => 'The provider template code must be a string.',

            'provider_configs.array' => 'The provider configs must be an array.',
            'provider_configs.min' => 'At least one variable mapping is required when provider configs are provided.',
            'provider_configs.required' => 'Variable mapping is required when the selected provider is an API provider.',
            'provider_configs.*.template_variable_id.required_with' => 'Each provider config requires a template variable.',
            'provider_configs.*.template_variable_id.distinct' => 'Each variable may only be mapped once.',
            'provider_configs.*.template_variable_id.exists' => 'One of the selected variables does not belong to this template.',
            'provider_configs.*.provider_key.required_with' => 'Each mapped variable needs a provider key.',
            'provider_configs.*.provider_key.string' => 'The provider key must be a string.',
        ];
    }
}
