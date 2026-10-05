<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLinkRequest extends FormRequest
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
            'link_id' => 'required|numeric|exists:payment_links,id', //

            'payment_id' => 'required|numeric|exists:payments,id',
            'amount' => 'required|numeric',
            'due_date' => 'required|date',
        ];
    }
}
