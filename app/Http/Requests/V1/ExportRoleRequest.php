<?php

namespace App\Http\Requests\V1;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class ExportRoleRequest extends Request
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
            'title' => 'sometimes|string',
            'description' => 'sometimes|string',
            'users_count' => 'sometimes|string',
            
           
            'created_at_from' => 'sometimes|date',
            'created_at_to' => 'sometimes|date|after_or_equal:created_at_from',
            
            'search' => 'sometimes|string',
            'discount' => 'sometimes|string',
            
            'ids' => 'sometimes|array|min:1',
            'ids.*'=>'integer',
            'columns' => 'sometimes|array|min:1',
            'columns.*' => ['string', 'distinct', Rule::in(['title', 'description', 'users_count', 'role', 'discount'])],
        ];
    }

    public function messages(): array
    {
        return [
            'created_at_to.after_or_equal'=>'The created at to date must be a date after or equal to created at from date.',
            'columns.*.distinct' => 'The columns must not contain duplicate values.',
            'columns.*.in' => 'The selected columns is invalid. Valid options are: title, description, user_count, role, discount',
            'ids.array'=>'The ids must be an array',
            'ids.*' => 'The select ids is invalid. must be an integer',
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
