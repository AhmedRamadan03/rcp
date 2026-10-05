<?php

namespace App\Http\Requests\V1\Settings\GetRequests;

use Illuminate\Foundation\Http\FormRequest;

class GetDefaultPermissionsRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'roleId' => 'required|integer|exists:roles,id'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'roleId.required' => 'Role ID is required',
            'roleId.integer' => 'Role ID must be an integer',
            'roleId.exists' => 'The selected role does not exist'
        ];
    }


    protected function prepareForValidation(): void
    {
        $this->merge([
            'roleId' => $this->route('roleId')
        ]);
    }

}