<?php

namespace App\Http\Requests\V1\Delegations\CreateRequests;

use App\Enums\Roles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DelegationStoreRequest extends FormRequest
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
            'from_user_id' => $this->getUserValidationRules(),
            'to_user_id' => array_merge($this->getUserValidationRules(), ['different:from_user_id']),
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'nullable|date|after:start_date',
        ];
    }

    protected function getUserValidationRules(): array
    {
        return [
            'required',
            'exists:users,id',
            Rule::exists('users', 'id')->where(function ($query) {
                $query->whereIn('role_id', [Roles::COLLECTOR->value, Roles::EXTERNAL_OFFICE->value]);
            }),
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'from_user_id.required' => 'Please select a delegator user.',
            'from_user_id.exists' => 'The from user must be either COLLECTOR or EXTERNAL_OFFICE.',
            'to_user_id.required' => 'Please select a delegate user.',
            'to_user_id.exists' => 'The to user must be either COLLECTOR or EXTERNAL_OFFICE.',
            'to_user_id.different' => 'You cannot delegate to yourself.',
            'start_date.required' => 'Start date is required.',
            'start_date.after_or_equal' => 'Start date cannot be in the past.',
            'end_date.after' => 'End date must be after start date.',
        ];
    }

}
