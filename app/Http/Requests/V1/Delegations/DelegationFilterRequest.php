<?php

namespace App\Http\Requests\V1\Delegations;

use App\Enums\DelegationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DelegationFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string'],
            'from_user_name' => ['sometimes', 'string'],
            'to_user_name' => ['sometimes', 'string'],

            'ids' => ['sometimes', 'array', 'min:1'],
            'ids.*' => ['numeric', 'exists:delegations,id'],

            'start_date_from' => ['sometimes', 'date'],
            'start_date_to' => ['sometimes', 'date', 'after_or_equal:start_date_from'],

            'end_date_from' => ['sometimes', 'date'],
            'end_date_to' => ['sometimes', 'date', 'after_or_equal:end_date_from'],

            'created_at_from' => ['sometimes', 'date'],
            'created_at_to' => ['sometimes', 'date', 'after_or_equal:created_at_from'],

            'duration_days' => ['sometimes', 'integer', 'min:0'],

            'status' => ['sometimes', 'array'],
            'status.*' => ['sometimes', 'string', Rule::enum(DelegationStatus::class)],
            
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],

            // Export fields
            'columns' => 'sometimes|array|min:1',
            'columns.*' => 'string|in:from_user_name,to_user_name,start_date,end_date,duration_days,status',

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