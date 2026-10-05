<?php

namespace App\Http\Requests\V1\Reports;

use App\Enums\ClaimStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DefaultOnPaymentFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assigned_to' => ['sometimes', 'string', 'max:255'],
            'claim_number' => ['sometimes', 'string', 'max:255'],
            'claim_status' => ['sometimes', 'array'],
            'claim_status.*' => ['sometimes', 'string', Rule::in(ClaimStatus::defaultInPaymentStatuses())],
            'claim_amount' => ['sometimes', 'numeric'],
            'claim_id' => 'sometimes|integer',

            'ids' => ['sometimes', 'array', 'min:1'],
            'ids.*' => ['numeric', 'exists:claims,id'],

            'accident_date_from' => ['sometimes', 'date'],
            'accident_date_to' => ['sometimes', 'date', 'after_or_equal:accident_date_from'],

            'intimation_date_from' => ['sometimes', 'date'],
            'intimation_date_to' => ['sometimes', 'date', 'after_or_equal:intimation_date_from'],

            'insertion_date_from' => ['sometimes', 'date'],
            'insertion_date_to' => ['sometimes', 'date', 'after_or_equal:insertion_date_from'],

            'search' => ['sometimes', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],

            // Export fields
            'columns' => 'sometimes|array|min:1',
            'columns.*' => 'string|in:id,assigned_to,claim_number,claim_status,claim_amount,accident_date,intimation_date,insertion_date',
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'claim_id.integer' => 'The claim id must be an integer.',
            'claim_status.array' => 'The claim status filter must be an array of values.',
            'claim_status.*.in' => 'One or more selected claim statuses are invalid.',
            'claim_amount.numeric' => 'The claim amount must be a number.',
            'ids.array' => 'The ids filter must be an array of claim ids.',
            'ids.min' => 'At least one claim id must be provided.',
            'ids.*.numeric' => 'Each claim id must be a number.',
            'ids.*.exists' => 'One or more selected claim ids do not exist.',

            'accident_date_to.after_or_equal' => 'The accident end date must be on or after the accident start date.',
            'intimation_date_to.after_or_equal' => 'The intimation end date must be on or after the intimation start date.',
            'insertion_date_to.after_or_equal' => 'The insertion end date must be on or after the insertion start date.',
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