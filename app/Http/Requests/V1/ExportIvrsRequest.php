<?php

namespace App\Http\Requests\V1;

use App\Http\Requests\Request;

class ExportIvrsRequest extends Request
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
            'status' => 'sometimes|array',
            'claim_number' => 'sometimes',
            'debtor_phone' => 'sometimes|string',
            
            'call_status' => 'sometimes|array',
            'call_duration' => 'sometimes',
            
            'call_date_from' => 'sometimes|before_or_equal:today',
            'call_date_to' => 'sometimes|after_or_equal:call_date_from',
            'created_at_from' => 'sometimes|before_or_equal:today',
            'created_at_to' => 'sometimes|after_or_equal:created_at_from',
            
            'columns' => 'sometimes|array|min:1',
            'columns.*' => 'string|in:claim_number,phone_number,call_date,call_status,call_duration',

            'ids' => 'sometimes|array|min:1',
            'ids.*' => 'numeric',
            'claim_id' => 'numeric',
        ];
    }


     public function filters(): array
    {
        return $this->only([ 'debtor_phone', 'claim_number', 'call_status', 'status', 'call_duration', 'call_date_from','call_date_to', 'search', 'created_at_from', 'created_at_to','ids']);
    }


    public function columns(): array
    {
        return $this->input('columns', []);
    }
}
