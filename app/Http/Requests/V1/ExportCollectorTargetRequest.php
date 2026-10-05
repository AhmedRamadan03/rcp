<?php

namespace App\Http\Requests\V1;

use App\Http\Requests\Request;

class ExportCollectorTargetRequest extends Request
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
            'target' => 'sometimes|numeric',
            'arcieved_target' => 'sometimes|numeric',
            'remaining_target' => 'sometimes|numeric',
            'start_date_from' => 'sometimes|date',
            'start_date_to' => 'sometimes|date|after_or_equal:start_date_from',
            'end_date_from' => 'sometimes|date',
            'end_date_to' => 'sometimes|date|after_or_equal:end_date_from',
            // 'start_date' => 'sometimes|date',
            // 'end_date' => 'sometimes|date|after_or_equal:start_date',
            'search' => 'sometimes',
            'created_at_from' => 'sometimes|date',
            'created_at_to' => 'sometimes|date|after_or_equal:created_at_from',
            'status' => 'sometimes|array',
            'status.*' => 'in:'.implode(',', array_map(fn($status) => $status->value, \App\Enums\TargetStatus::cases())),
            'ids' => 'sometimes|array|min:1',
            'ids.*'=>'numeric',
            'columns' => 'sometimes|array|min:1',
            'columns.*' => 'string|distinct|in:target,arcieved_target,remaining_target,start_date,end_date,status',
        ];
    }

    public function messages(): array
    {
        return [
            'start_date_to.after_or_equal' => 'The end date must be a date after or equal to start Date from.',
            'end_date_to.after_or_equal' => 'The end date must be a date after or equal to end Date from.',
            'created_at_to.after_or_equal' => 'The created at to date must be a date after or equal to created at from date.',
            'columns.*.distinct' => 'The columns must not contain duplicate values.',
            'columns.*.in' => 'The selected columns is invalid. Valid options are: target, arcieved_target, remaining_target, start_date, end_date, status.',
            'ids.*' => 'The select ids is invalid. this id not found',
            'status.array' => 'The select status is invalid. must be array',
            'status.*' => 'The selected status is invalid Valid options are:'.implode(',', array_map(fn($status) => $status->value, \App\Enums\TargetStatus::cases())),
        ];
    }

    public function filters(): array
    {
        return $this->only(['target', 'arcieved_target', 'remaining_target', 'start_date_from', 'start_date_to', 'end_date_from', 'end_date_to', 'status', 'search', 'created_at_from', 'created_at_to','ids']);
    }


    public function columns(): array
    {
        return $this->input('columns', []);
    }
}
