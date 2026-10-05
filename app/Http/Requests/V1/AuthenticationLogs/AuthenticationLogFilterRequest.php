<?php

namespace App\Http\Requests\V1\AuthenticationLogs;

use Illuminate\Foundation\Http\FormRequest;

class AuthenticationLogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('login_successful')) {
            $this->merge([
                'login_successful' => filter_var(
                    $this->input('login_successful'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ),
            ]);
        }
    }
    public function rules(): array
    {
        return [
            'ids' => ['sometimes', 'array', 'min:1'],
            'ids.*' => ['numeric', 'exists:authentication_log,id'],

            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'max:255'],
            'ip_address' => ['sometimes', 'string', 'max:45'],
            'login_successful' => ['sometimes', 'boolean'],

            'login_at_from' => ['sometimes', 'date'],
            'login_at_to' => ['sometimes', 'date', 'after_or_equal:login_at_from'],

            'logout_at_from' => ['sometimes', 'date'],
            'logout_at_to' => ['sometimes', 'date', 'after_or_equal:logout_at_from'],

            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],

            // Export fields
            'columns' => 'sometimes|array|min:1',
            'columns.*' => 'string|in:name,email,ip_address,user_agent,login_at,login_status,logout_at',
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'ids.array' => 'The ids filter must be an array of log ids.',
            'ids.min' => 'At least one log id must be provided.',
            'ids.*.numeric' => 'Each log id must be a number.',
            'ids.*.exists' => 'One or more selected log ids do not exist.',

            'login_at_to.after_or_equal' => 'The login end date must be on or after the login start date.',
            'logout_at_to.after_or_equal' => 'The logout end date must be on or after the logout start date.',

            'columns.array' => 'The columns field must be an array.',
            'columns.min' => 'At least one column must be selected.',
            'columns.*.in' => 'One or more selected columns are invalid.',
        ];
    }

    /**
     * Get only the filter parameters (excluding pagination, columns, and other meta).
     */
    public function filters(): array
    {
        return collect($this->validated())
            ->except(['per_page', 'columns'])
            ->filter(fn($value) => $value !== null)
            ->toArray();
    }

    /**
     * Get the pagination limit (if present).
     */
    public function perPage(): ?int
    {
        return $this->input('per_page');
    }

    public function columns(): ?array
    {
        return $this->input('columns');
    }
}