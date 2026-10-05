<?php

namespace App\Http\Requests\V1;

use App\Http\Requests\Request as BaseRequest;

class StorePaymentLinkRequest extends BaseRequest
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
            //
            'payment_id' => 'required|exists:payments,id', //
            'due_date' => 'required|date',
            'amount' => 'required|numeric',
        ];
    }

    public function messages()
    {
        return [
            'payment_id.required' => 'payments is required',
            'payment_id.exists' => 'payment does not exists',
            'due_date.required' => 'due date is required and must be date',
            'amount.required' => 'amount is required and must be number',
        ];
    }
}
