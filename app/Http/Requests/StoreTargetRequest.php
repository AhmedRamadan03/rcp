<?php

namespace App\Http\Requests;

use App\Enums\TargetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
class StoreTargetRequest extends FormRequest
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
            'target' => 'required|numeric|min:1',
            'target_type' => 'required|in:' . implode(',', array_column(TargetType::cases(), 'value')),
            'collector_ids' => 'required|array',
            'collector_ids.*' => 'integer|exists:users,id',
            'is_reoccurring' => 'boolean',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status' => false,
            'errors' => $validator->errors(),
        ], 422));
    }
}
