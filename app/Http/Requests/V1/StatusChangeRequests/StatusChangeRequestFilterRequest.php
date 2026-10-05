<?php

namespace App\Http\Requests\V1\StatusChangeRequests;

use App\Enums\ClaimStatus;
use App\Enums\PermissionRequestsStatus;
use App\Enums\RequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StatusChangeRequestFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowedTransitionStatuses = array_merge(
            array_column(ClaimStatus::cases(), 'value'),
            array_column(PermissionRequestsStatus::cases(), 'value')
        );
        return [
            'ids' => 'sometimes|array|min:1',
            'ids.*' => 'numeric|exists:status_change_requests,id',

            'claim_id' => 'sometimes|integer',
            'claim_number' => 'sometimes|string|max:255',
            'requested_by' => 'sometimes|string|max:255',
            'supervisor_name' => 'sometimes|string|max:255',
            'note' => 'sometimes|string|max:255',
            'search' => 'sometimes|string|max:255',

            'from_status' => 'sometimes|array',
            'from_status.*' => ['sometimes', 'string', Rule::in($allowedTransitionStatuses)],

            'to_status' => 'sometimes|array',
            'to_status.*' => ['sometimes', 'string', Rule::in($allowedTransitionStatuses)],

            'status' => 'sometimes|array',
            'status.*' => ['sometimes', 'string', Rule::enum(RequestStatus::class)],

            'created_at_from' => 'sometimes|date',
            'created_at_to' => 'sometimes|date|after_or_equal:created_at_from',

            // Export fields
            'columns' => 'sometimes|array|min:1',
            'columns.*' => 'string|in:claim_id,claim_number,request_by,from_status,to_status,note,created_at,status,supervisor_name',

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
            'ids.array' => 'The ids filter must be an array of request ids.',
            'ids.min' => 'At least one request id must be provided.',
            'ids.*.numeric' => 'Each id must be a number.',
            'ids.*.exists' => 'One or more selected ids do not exist.',

            'from_status.array' => 'The from status filter must be an array of values.',
            'from_status.*.enum' => 'One or more selected from statuses are invalid.',

            'to_status.array' => 'The to status filter must be an array of values.',
            'to_status.*.enum' => 'One or more selected to statuses are invalid.',

            'status.array' => 'The status filter must be an array of values.',
            'status.*.enum' => 'One or more selected statuses are invalid.',

            'created_at_to.after_or_equal' => 'The end date must be on or after the start date.',

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
     * Get the pagination limit
     */
    public function perPage(): ?int
    {
        return $this->input('per_page');
    }

    /**
     * Get the selected columns for export
     */
    public function columns(): ?array
    {
        return $this->input('columns');
    }
}