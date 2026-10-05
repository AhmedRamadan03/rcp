<?php

namespace App\Http\Requests\V1;

use App\Http\Requests\Request;

class ExportTargetDetailsRequest extends Request
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
            'collected' => 'sometimes|numeric',
            'claim_number' => 'sometimes',
            'installment_number' => 'sometimes',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
            'search' => 'sometimes',
            'created_at_from' => 'sometimes|date',
            'created_at_to' => 'sometimes|date|after_or_equal:created_at_from',
            'ids' => 'sometimes|array|min:1',
            'ids.*'=>'numeric',
            'columns' => 'sometimes|array|min:1',
            'columns.*' => 'string|distinct|in:collected,date,claim_number,installment_number,',
        ];
    }

    public function messages(): array
    {
        return [
            'created_at_to.after_or_equal' => 'The created at to date must be a date after or equal to created at from date.',
            'date_to.after_or_equal' => 'The date to  must be a date after or equal to date from .',
            'columns.*.distinct' => 'The columns must not contain duplicate values.',
            'columns.*.in' => 'The selected columns is invalid. Valid options are: collected, date, claim_number, installment_number.',
            'ids.*' => 'The select ids is invalid. this id not found'

        ];
    }

    public function filters(): array
    {
        return $this->only(['collected', 'date_from', 'date_to', 'claim_number', 'installment_number', 'search', 'created_at_from', 'created_at_to','ids']);
    }


    public function columns(): array
    {
        return $this->input('columns', []);
    }
}
