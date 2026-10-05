<?php

namespace App\Http\Requests\V1;

use App\Enums\ClaimFileAccess;
use App\Http\Requests\Request as BaseRequest;
use Illuminate\Validation\Rules\Enum;

class ClaimFileUploadedRequest extends BaseRequest
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
            'claim_id' => 'required|numeric|exists:claims,id', //
            'file' => 'required|file', // |exists:users,id
            'title' => 'required', // |exists:users,id
            'access' => [new Enum(ClaimFileAccess::class)],
        ];
    }
}
