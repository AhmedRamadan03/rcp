<?php

namespace App\Http\Requests\V1;

use App\Http\Requests\Request as BaseRequest;

class StoreRejectionsFilesRequest extends BaseRequest
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
            'rejection_id' => 'required|numeric|exists|rejected_claims|id', //
            'files' => 'required|array|min:1',
            'files.*' => 'file',

        ];
    }
}
