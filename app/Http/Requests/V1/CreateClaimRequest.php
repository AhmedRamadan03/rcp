<?php

namespace App\Http\Requests\V1;

use App\Enums\ClaimType;
use App\Enums\DebtorType;
use App\Http\Requests\Request as BaseRequest;
use Illuminate\Validation\Rules\Enum;

class CreateClaimRequest extends BaseRequest
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
            'client_id' => 'required|numeric',
            'user_id' => 'required|numeric',
            'amount' => 'required|numeric',
            'type' => ['required', new Enum(ClaimType::class)],
            'city_id' => 'required',
            'debtor_type' => ['required', new Enum(DebtorType::class)],
            'policy_number' => 'required',
            'claim_number' => 'required',
            'debtor_name' => ['required'],
            'debtor_nid' => ['required', 'numeric'],
            'accident_date' => ['required', 'date'],
            'automatic_assinging' => ['boolean'],
            'debtor_phone' => 'required',
        ];
    }

    public function messages()
    {
        return [
            'client_id.required' => __('validation.required'),
            'client_id.numeric' => __('validation.numeric'),
        ];
    }
}
