<?php

namespace App\Http\Requests\V1\Settings;

use App\Enums\BankAccountStatusEnum;
use App\Http\Requests\Request as BaseRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class GeneratePartnerApiRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        
        return [
            'title' => 'required|unique:partner_api_credentials,title|string|max:255',
        ];
    }

   
}