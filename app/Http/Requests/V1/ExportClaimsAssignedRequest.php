<?php

namespace App\Http\Requests\V1;

use App\Http\Requests\Request;

class ExportClaimsAssignedRequest extends Request
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
            'claim_number' => 'sometimes',
            'supervisor' => 'sometimes|string',
            'from_user' => 'sometimes|string',
            'to_user' => 'sometimes|string',
            'assign_date_from' => 'sometimes|date',
            'assign_date_to' => 'sometimes|date|after_or_equal:assign_date_from',
            'status' => 'sometimes|array',
            'status.*' => 'string|in:0,1',
            
            'created_at_from' => 'sometimes|before_or_equal:today',
            'created_at_to' => 'sometimes|after_or_equal:created_at_from',
            
            'columns' => 'sometimes|array|min:1',
            'columns.*' => 'string|in:claim_number,supervisor,from_user,to_user,assign_date,status',

            'ids' => 'sometimes|array|min:1',
            'ids.*' => 'numeric',
        ];
    }


     public function filters(): array
    {
        return $this->only(['claim_number', 'supervisor', 'from_user', 'to_user', 'assign_date_from', 'assign_date_to', 'search', 'created_at_from', 'created_at_to', 'status', 'ids']);
    }


    public function columns(): array
    {
        return $this->input('columns', []);
    }
}
