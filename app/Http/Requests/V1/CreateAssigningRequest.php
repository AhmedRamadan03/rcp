<?php

namespace App\Http\Requests\V1;

use App\Http\Requests\Request as BaseRequest;

class CreateAssigningRequest extends BaseRequest
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
            'claims_id' => 'required|array|min:1', //
            'claims_id.*' => 'required|numeric|exists:claims,id', //
            'supervisor_id' => 'required|numeric', // |exists:users,id
            'assigns_to' => 'required|array|min:1', // |exists:users,id
            'assigns_to.*' => 'required|numeric', // |exists:users,id

        ];
    }

    public function messages()
    {
        return [
            'claims_id.*.exists' => 'claim not found in database',
        ];
    }
}
