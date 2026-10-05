<?php

namespace App\Http\Requests\V1\Claims;

use App\Enums\UploadFileType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class PayInstallmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
       return [
            'user_email'=> 'nullable|email|exists:users,email',
            'note'=> 'required|string|min:6',
            'invoice_file' => [
                'required',
                'file',
                'max:20480',
                'mimes:' . implode(',', UploadFileType::extensionsWithoutSvg()),
            ],
        ];
    }


    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'error' => true,
            'message' => 'Validation errors',
            'errors' => $validator->errors(),
        ], 422));
    }


     
}