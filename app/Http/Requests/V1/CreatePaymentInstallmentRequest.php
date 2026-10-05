<?php

namespace App\Http\Requests\V1;

use App\Http\Requests\Request as BaseRequest;

class CreatePaymentInstallmentRequest extends BaseRequest
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

            'installment_count' => 'required|numeric',
            'payment_id' => 'required|numeric|exists:payments,id',

        ];
    }

    public function messages()
    {
        return [
            'payment_id' => 'payment id does not found in database or required',
            'installment_count' => 'installment_count is required',
        ];
    }
}
