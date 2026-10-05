<?php

namespace App\Http\Requests\V1\Settings\CreateRequests;

use App\Enums\ThirdPartyType;
use App\Models\ThirdParty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CustomTemplateStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                Rule::unique('custom_templates', 'name')
                    ->where(fn($query) => $query->where('template_id', $this->template_id)),
            ],
            'body' => 'required|string',
            'template_id' => 'required|exists:message_templates,id',
            'is_active' => 'required|in:0,1',
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
                Rule::requiredIf(fn() => filled($this->provider_configs)),
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
                    ->where(fn($query) => $query->where('template_id', $this->template_id)),
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

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The template name is required.',
            'name.string' => 'The template name must be a string.',
            'name.unique' => 'The template name must be unique.',
            'body.required' => 'The template body is required.',
            'body.string' => 'The template body must be a string.',
            'template_id.required' => 'The template ID is required.',
            'template_id.exists' => 'The selected template does not exist.',
            'is_active.required' => 'The active status is required.',
            'is_active.in' => 'The active status must be either 1 or 0.',

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
