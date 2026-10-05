<?php

namespace App\Http\Requests\V1;

use App\Http\Requests\Request;

class ExportDataHistoryRequest extends Request
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
            'request_no' => 'sometimes|integer',
            'request_name' => 'sometimes|string',
            'status' => 'sometimes|array',
            'status.*' => 'sometimes|in:'. implode(',', \App\Enums\DataHistoryEnum::getReturnStatuses()),
            'request_at_from' => 'sometimes|before_or_equal:today',
            'request_at_to' => 'sometimes|after_or_equal:request_at_from',
            'replied_at_from' => 'sometimes|before_or_equal:today',
            'replied_at_to' => 'sometimes|after_or_equal:replied_at_from',
            'type'=> 'sometimes|array',
            'type.*'=> 'in:'. implode(',', \App\Enums\DataHistoryEnum::getReturnTypes()),
            'search' => 'sometimes|string',
            'columns' => 'sometimes|array|min:1',
            'columns.*' => 'string|distinct|in:request_no,request_name,request_type,status,request_at,replied_at,filters',
            'ids' => 'sometimes|array|min:1',
            'ids.*' => 'numeric',
        ];
    }


     public function messages(): array
    {
        return [
            'type'=>'Type must be array',
            'type.*.in'=>'Value not exists must be : '. implode(',', \App\Enums\DataHistoryEnum::getReturnTypes()),
            'request_at_to.after_or_equal' => 'The created at to date must be a date after or equal to request_at_from date.',
            'replied_at_to.after_or_equal' => 'The date to  must be a date after or equal to replied_at_from.',
            'columns.*.distinct' => 'The columns must not contain duplicate values.',
            'columns.*.in' => 'The selected columns is invalid. Valid options are:request_no,request_name,request_type,status,request_at,replied_at,filters',
            'ids.*' => 'The select ids is invalid. this id not found'

        ];
    }

}
