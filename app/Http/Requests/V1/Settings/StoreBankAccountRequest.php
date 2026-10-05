<?php

namespace App\Http\Requests\V1\Settings;

use App\Enums\BankAccountStatusEnum;
use App\Http\Requests\Request as BaseRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreBankAccountRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        
        return [
            'bank_name'    => 'required|string|max:255',
            'account_name' => 'required|string|max:255',

            'account_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('bank_accounts', 'account_number')
                    ->ignore($this->id), // اسم الـ route model
            ],

            'iban' => [
                'required',
                'string',
                Rule::unique('bank_accounts', 'iban')
                    ->ignore($this->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [

            '.required' => 'The :attribute field is required.',
            '.string' => 'The :attribute field must be a string.',
            '.max' => 'The :attribute field must not exceed :max characters.',
        ];
    }
}
