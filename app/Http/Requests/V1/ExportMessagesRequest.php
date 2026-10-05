<?php

namespace App\Http\Requests\V1;

use App\Http\Requests\Request;

class ExportMessagesRequest extends Request
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
            'search' => 'sometimes|string',
            'claim_id' => 'sometimes|numeric',
            'debtor_phone' => 'sometimes|string',
            'claim_number' => 'sometimes',
            'national_id' => 'sometimes',
            'message_body' => 'sometimes|string',
            'message_type' => 'sometimes|in:true,false',
            
            'status' => 'sometimes|array',
            'message_status' => 'sometimes|string',
            'date_from' => 'sometimes|before_or_equal:today',
            'date_to' => 'sometimes|after_or_equal:date_from',
            'created_at_from' => 'sometimes|before_or_equal:today',
            'created_at_to' => 'sometimes|after_or_equal:created_at_from',
            
            'columns' => 'sometimes|array|min:1',
            'columns.*' => 'string|in:claim_number,phone_number,message_type,debtor_id,message_body,status,message_status,date',

            'ids' => 'sometimes|array|min:1',
            'ids.*' => 'numeric',
        ];
    }


     public function filters(): array
    {
        return $this->only(['claim_id', 'debtor_phone', 'claim_number', 'national_id', 'message_body', 'status', 'message_status', 'date_from','date_to', 'search', 'created_at_from','message_type', 'created_at_to','ids']);
    }


    public function columns(): array
    {
        return $this->input('columns', []);
    }
}
