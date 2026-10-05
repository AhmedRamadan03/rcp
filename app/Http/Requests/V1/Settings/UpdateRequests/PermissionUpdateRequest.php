<?php

namespace App\Http\Requests\V1\Settings\UpdateRequests;

use Illuminate\Foundation\Http\FormRequest;

class PermissionUpdateRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id',
            'permissions' => 'required|array',
            'permissions.*' => 'required|array',
            'permissions.*.*' => 'required',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'User ID is required',
            'user_id.exists' => 'User does not exist',
            'role_id.required' => 'Role ID is required',
            'role_id.exists' => 'Role does not exist',
            'permissions.required' => 'Permissions object is required',
            'permissions.*.required' => 'All permission categories are required',
            'permissions.*.array' => 'Permission category must be an array',
            'permissions.*.*.required' => 'All permissions within each category are required',
            'permissions.*.*.in' => 'Permission value must be either 0 or 1',
        ];
    }

    /**
     * Get validated data formatted for the service
     */
    public function getFormattedData(): array
    {
        $validated = $this->validated();

        // Force specific fields to always be 0
        // $validated['permissions']['settings']['create_settings'] = 0;
        // $validated['permissions']['settings']['update_settings'] = 0;
        // $validated['permissions']['settings']['delete_settings'] = 0;

        // $validated['permissions']['permissions']['create_permissions'] = 0;
        // $validated['permissions']['role']['show_role'] = 0;

        return array_merge(
            [
                'user_id' => $validated['user_id'],
                'role_id' => $validated['role_id'],
            ],
            $validated['permissions']
        );
    }


    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $permissions = $this->input('permissions', []);

            foreach ($permissions as $category => $perms) {
                if (!is_array($perms)) continue;

                foreach ($perms as $key => $value) {
                    // قديم = 0 أو 1
                    if (is_numeric($value)) {
                        if (!in_array((int) $value, [0, 1])) {
                            $validator->errors()->add(
                                "permissions.{$category}.{$key}",
                                "Permission value must be 0, 1, or an object"
                            );
                        }
                        continue;
                    }

                    // جديد = object
                    if (is_array($value)) {
                        // granted لازم تكون موجودة دايماً
                        if (!isset($value['granted']) || !in_array((int) $value['granted'], [0, 1])) {
                            $validator->errors()->add(
                                "permissions.{$category}.{$key}.granted",
                                "granted is required and must be 0 or 1"
                            );
                        }

                        // أي مفتاح زيادة (with_approve, show_all, ...) لازم يكون 0 أو 1 لو موجود
                        foreach ($value as $modKey => $modValue) {
                            if ($modKey === 'granted') continue;

                            if (!in_array((int) $modValue, [0, 1], true) && !is_numeric($modValue)) {
                                $validator->errors()->add(
                                    "permissions.{$category}.{$key}.{$modKey}",
                                    "{$modKey} must be 0 or 1"
                                );
                            }
                        }

                        continue;
                    }

                    $validator->errors()->add(
                        "permissions.{$category}.{$key}",
                        "Invalid permission format"
                    );
                }
            }
        });
    }
}
