<?php

namespace App\Http\Requests\V1;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class ExportUsersRequest extends Request
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
            'name' => 'sometimes|string',
            'supervisor' => 'sometimes|string',
            'phone' => 'sometimes|string',
            
            'last_active_from' => 'sometimes|date',
            'last_active_to' => 'sometimes|date|after_or_equal:last_active_from',
            'created_at_from' => 'sometimes|date',
            'created_at_to' => 'sometimes|date|after_or_equal:created_at_from',
            'user_type' => 'sometimes|array',
            'user_type.*' => [Rule::enum(\App\Enums\UserType::class)],
            'search' => 'sometimes|string',
            
            'ids' => 'sometimes|array|min:1',
            'ids.*'=>'integer',
            'columns' => 'sometimes|array|min:1',
            'columns.*' => ['string', 'distinct', Rule::in(['name', 'supervisor', 'phone', 'role', 'last_active', 'user_creation_date', 'user_type'])],
            'role_id' => 'nullable|string|regex:/^[0-9,]+$/',
        ];
    }

    public function messages(): array
    {
        return [
            'last_active_to.after_or_equal' => 'The end date must be a date after or equal to last active date from.',
            'created_at_to.after_or_equal' => 'The created at to date must be a date after or equal to created at from date.',
            'columns.*.distinct' => 'The columns must not contain duplicate values.',
            'columns.*.in' => 'The selected columns is invalid. Valid options are: name, supervisor, phone, role, last_active, user_creation_date, user_type',
            'ids.array'=>'The ids must be an array',
            'ids.*' => 'The select ids is invalid. must be an integer',
            'user_type.array'=>'The user type must br an array',
            'user_type.*' => 'The selected user type is invalid. valid options are: '.implode(',', array_map(fn($status) => $status->value, \App\Enums\UserType::cases())),
        ];
    }

    public function filters(): array
    {
        return collect($this->validated())->except('columns')->toArray();    
    }


    public function columns(): array
    {
        return $this->input('columns', []);
    }
}
