<?php

namespace App\Http\Requests\V1;

use App\Http\Requests\Request;

class ExportAccountingReportRequest extends Request
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
            'claim_id' => 'sometimes|numeric',
            'claim_number' => 'sometimes|string',
            'claim_amount' => 'sometimes',
            'invoice_status' => 'sometimes|array',
            'invoice_status.*'=>'in:'.implode(',', array_map(fn($status) => $status->value, \App\Enums\InvoiceStatus::cases())),
            'invoice_date_from' => 'sometimes|date',
            'invoice_date_to' => 'sometimes|date|after_or_equal:invoice_date_from',
            'created_at_from' => 'sometimes|date',
            'created_at_to' => 'sometimes|date|after_or_equal:created_at_from',
            'user_type' => 'sometimes|array',
            'user_type.*' => 'in:'.implode(',', array_map(fn($status) => $status->value, \App\Enums\UserType::cases())),
            'search' => 'sometimes',
            
            'ids' => 'sometimes|array|min:1',
            'ids.*'=>'numeric',
            'columns' => 'sometimes|array|min:1',
            'columns.*' => 'string|distinct|in:claim_id,claim_number,claim_amount,invoiced_status,invoiced_date,claim_creation_date,user_type',
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_date_to.after_or_equal' => 'The end date must be a date after or equal to invoice date from.',
            'created_at_to.after_or_equal' => 'The created at to date must be a date after or equal to created at from date.',
            'columns.*.distinct' => 'The columns must not contain duplicate values.',
            'columns.*.in' => 'The selected columns is invalid. Valid options are: claim_id, claim_number, claim_amount, invoiced_status, invoiced_date, claim_creation_date, user_type',
            'ids.*' => 'The select ids is invalid. this id not found'
        ];
    }

    public function filters(): array
    {
        return $this->only(['claim_id', 'claim_number', 'claim_amount', 'invoice_status', 'invoice_date_from', 'invoice_date_to', 'created_at_from', 'created_at_to', 'search', 'user_type','ids']);
    }


    public function columns(): array
    {
        return $this->input('columns', []);
    }
}
