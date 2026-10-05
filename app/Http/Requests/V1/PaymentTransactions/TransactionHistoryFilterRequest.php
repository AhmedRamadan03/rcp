<?php

namespace App\Http\Requests\V1\PaymentTransactions;

use App\Enums\PaymentTransactionStatus;
use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionHistoryFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Filter fields
            'search' => 'sometimes|string|max:255',
            'payment_id' => 'nullable|string',
            'card_brand' => 'nullable|string',
            'masked_pan' => 'nullable|string',
            'rrn' => 'nullable|string',
            'amount' => 'sometimes|integer',
            'claim_id' => 'sometimes|integer',
            'claim_number' => 'sometimes|string|max:255',
            'collector_name' => 'sometimes|string|max:255',
            'installment_number' => 'sometimes|integer',
            'created_at_from' => 'sometimes|date',
            'created_at_to' => 'sometimes|date|after_or_equal:created_at_from',

            'ids' => 'sometimes|array|min:1',
            'ids.*' => 'numeric|exists:payment_transactions,id',

            'status' => 'sometimes|array',
            'status.*' => ['sometimes', 'string', Rule::enum(PaymentTransactionStatus::class)],

            'payment_type' => 'sometimes|array',
            'payment_type.*' => ['sometimes', 'string', Rule::enum(TransactionType::class)],

            // Export fields
            'columns' => 'sometimes|array|min:1',
            'columns.*' => 'string|in:claim_id,amount,claim_number,collector_name,installment_number,created_at,status,payment_type,rrn,payment_id,card_brand,masked_pan',
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

            'ids.array' => 'The ids filter must be an array of transaction ids.',
            'ids.min' => 'At least one transaction id must be provided.',
            'ids.*.numeric' => 'Each transaction id must be a number.',
            'ids.*.exists' => 'One or more selected transaction ids do not exist.',
            
            'amount.integer' => 'The amount must be an integer.',
            'collector_name.string' => 'The collector name must be a string.',
            'installment_number.integer' => 'The installment number must be an integer.',

            'status.array' => 'The status filter must be an array of values.',
            'status.*.enum' => 'One or more selected statuses are invalid.',

            'payment_type.array' => 'The payment type filter must be an array of values.',
            'payment_type.*.enum' => 'One or more selected payment types are invalid.',

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

    /**
     * Get the selected columns for export (if present).
     */
    public function columns(): ?array
    {
        return $this->input('columns');
    }

}