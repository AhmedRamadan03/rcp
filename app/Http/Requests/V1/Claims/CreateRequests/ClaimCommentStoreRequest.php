<?php

namespace App\Http\Requests\V1\Claims\CreateRequests;

use App\Enums\Roles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class ClaimCommentStoreRequest extends FormRequest
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
            'body' => 'required|string',
            'reminder_date' => 'nullable|date',
            'is_court_reminder' => $this->getCourtReminderValidationRules(),
        ];
    }

    protected function getCourtReminderValidationRules(): array
    {
        return [
            'nullable',
            'boolean',
            function ($attribute, $value, $fail) {
                if ($value && auth()->user()->role_id !== Roles::LEGAL_DEPARTMENT->value) {
                    $fail('Only Legal Department can set a court reminder.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'The comment body is required.',
            'body.string' => 'The comment body must be text.',
            'body.max' => 'The comment body cannot exceed 255 characters.',
            'reminder_date.date' => 'The reminder date must be a valid date.',
            'is_court_reminder.boolean' => 'Court reminder must be true or false.',
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
