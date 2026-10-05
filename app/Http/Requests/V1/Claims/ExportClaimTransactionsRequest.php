<?php

namespace App\Http\Requests\V1\Claims;

use App\Http\Requests\Request;

class ExportClaimTransactionsRequest extends Request
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_link_id' => 'nullable|integer|exists:payment_links,id',
            'search' => 'nullable|string',
            'payment_id' => 'nullable|string',
            'card_brand' => 'nullable|string',
            'masked_pan' => 'nullable|string',
            'rrn' => 'nullable|string',
            'status' => 'nullable|array',
            'status.*' => 'string|in:'.implode(',', array_map(fn($status) => $status->value, \App\Enums\PaymentTransactionStatus::cases())),
            'payment_type' => 'nullable|array',
            'payment_type.*' => 'string|in:'. implode(',', array_map(fn($status) => $status->value, \App\Enums\TransactionType::cases())),
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'columns' => 'sometimes|array|min:1',
            'columns.*' => 'string|in:payment_id,card_brand,masked_pan,payment_type,date,status,rrn',
            'ids' => 'sometimes|array|min:1',
            'ids.*'=>'numeric'
        ];
    }


     public function messages(): array
    {
        return [
            'status.in' => 'The selected status is invalid. Valid options are: '.implode(',', array_map(fn($status) => $status->value, \App\Enums\PaymentTransactionStatus::cases())),
            'payment_type.in' => 'The selected payment type is invalid. Valid options are: '. implode(',', array_map(fn($status) => $status->value, \App\Enums\TransactionType::cases())),
            'columns.*.distinct' => 'The columns must not contain duplicate values.',

            'columns.*.in' => 'The selected columns is invalid. Valid options are: payment_id, card_brand, masked_pan, payment_type, date, status, rrn',
            'ids.*' => 'The select ids is invalid. this id not found'
        ];
    }

     public function filters(): array
    {
        return $this->only(['payment_link_id', 'search', 'status', 'invoice_status', 'payment_type', 'date_from', 'date_to', 'ids','masked_pan', 'payment_id', 'card_brand', 'rrn']);
    }

    public function columns(): array
    {
        return $this->input('columns', []);
    }
}
