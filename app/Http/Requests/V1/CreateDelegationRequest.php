<?php

namespace App\Http\Requests\V1;

use App\Http\Requests\Request as BaseRequest;

class CreateDelegationRequest extends BaseRequest
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
            'claim_id' => 'required|numeric|exists:claims,id', //
            'delegate_to' => 'required|numeric', // |exists:users,id
            'supervisor_id' => 'required|numeric', // |exists:users,id
            'comment' => 'required', // |exists:users,id
            'expiration_date' => 'required|date',

        ];
    }
}
