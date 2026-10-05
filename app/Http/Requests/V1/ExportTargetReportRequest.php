<?php

namespace App\Http\Requests\V1;

use App\Http\Requests\Request;

class ExportTargetReportRequest extends Request
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
            'officer' => 'sometimes|string',
            'search' => 'sometimes|string',
            'total_claims' => 'sometimes|numeric',
            'arcieved_target' => 'sometimes|numeric',
            'remaining_target' => 'sometimes|numeric',
            'total_target' => 'sometimes|numeric',
            'created_at_from'=>'sometimes|date',
            'created_at_to'=>'sometimes|date|after_or_equal:created_at_from',
            'ids' => 'sometimes|array|min:1',
            'ids.*'=>'numeric',
            'columns' => 'sometimes|array|min:1',
            'columns.*' => 'string|distinct|in:officer,total_claims,arcieved_target,remaining_target,total_target',
        ];
    }

    public function messages(): array
    {
        return [
            'created_at_to.after_or_equal' => 'The created at to date must be a date after or equal to created at from date.',
            'columns.*.distinct' => 'The columns must not contain duplicate values.',
            'columns.*.in' => 'The selected columns is invalid. Valid options are: officer, total_claims, arcieved_target, remaining_target, total_target.',
            'ids.*' => 'The select ids is invalid. this id not found'
        ];
    }


    public function filters(): array
    {
        return $this->only(['officer', 'total_claims', 'arcieved_target', 'remaining_target', 'total_target','ids','created_at_to','created_at_from','search']);
    }


    public function columns(): array
    {
        return $this->input('columns', []);
    }
}
