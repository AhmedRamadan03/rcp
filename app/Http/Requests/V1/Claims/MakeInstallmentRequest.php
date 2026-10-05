<?php

namespace App\Http\Requests\V1\Claims;

use App\Enums\Roles;
use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class MakeInstallmentRequest extends Request
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules =  [
            'installments' => 'required|array',
            'installments.*.amount' => 'required|numeric',
            'installments.*.date' => [
                'required',
                'date',
                'after_or_equal:today',
            ],
            'installments.*.order' => 'required|integer|min:1',
            'installments.*.id' => 'nullable|integer',
            'installments.*.payment_link_id' => 'nullable|integer',
            
        ];

        // Add conditional validation for 'note' based on user role to differentiate between system and partner API requests
        if (auth()->user()) {
            $rules['note'] =  auth()->user()->role_id == Roles::COLLECTOR->value || auth()->user()->role_id == Roles::EXTERNAL_OFFICE->value ? 'required|min:6' : 'nullable';
            
        } else {
            $rules['note'] = 'nullable';   // if request form Partner API.
            
        }


        return $rules;
        


    }


     
}