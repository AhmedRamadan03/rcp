<?php

namespace App\Http\Requests\V1\RejectedClaims;

use App\Enums\RejectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RejectedClaimFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Filter fields
            'search' => 'sometimes|string|max:255',
            'claim_id' => 'sometimes|integer',
            'claim_number' => 'sometimes|string|max:255',
            'reason' => 'sometimes|string|max:255',
            'status' => 'sometimes|array',
            'status.*' => ['sometimes', 'string', Rule::enum(RejectStatus::class)],
            'approved_by' => 'sometimes|string|max:255',
            'ids' => 'sometimes|array|min:1',
            'ids.*' => 'numeric|exists:claims,id',

            // Export fields
            'columns' => 'sometimes|array|min:1',
            'columns.*' => 'string|in:claim_id,claim_number,reason,status,approved_by',

            // Pagination (only for index)
            'per_page' => 'sometimes|integer|min:1|max:100',
        ];
    }


    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'claim_id.integer' => 'The claim id must be an integer.',

            'status.array' => 'The status filter must be an array.',
            'status.*.enum' => 'One or more selected statuses are invalid.',

            'ids.array' => 'The ids filter must be an array.',
            'ids.min' => 'At least one claim id must be provided.',
            'ids.*.exists' => 'One or more selected claim ids do not exist.',

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
            ->filter(fn ($value) => $value !== null)
            ->toArray();
    }

    /**
     * Get the pagination limit (if present).
     */
    public function perPage(): ?int
    {
        return $this->input('per_page');
    }

    /**
     * Get the selected columns for export (if present).
     */
    public function columns(): ?array
    {
        return $this->input('columns');
    }
}