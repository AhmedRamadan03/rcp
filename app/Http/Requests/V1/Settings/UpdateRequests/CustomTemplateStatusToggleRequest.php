<?php

namespace App\Http\Requests\V1\Settings\UpdateRequests;

use Illuminate\Foundation\Http\FormRequest;

class CustomTemplateStatusToggleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'custom_template_id' => 'required|exists:custom_templates,id'
        ];
    }

    public function messages(): array
    {
        return [
            'custom_template_id.required' => 'The template ID is required.',
            'custom_template_id.exists' => 'The selected template does not exist.',
        ];
    }
}