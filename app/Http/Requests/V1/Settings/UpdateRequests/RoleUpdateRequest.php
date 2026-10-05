<?php

namespace App\Http\Requests\V1\Settings\UpdateRequests;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class RoleUpdateRequest extends FormRequest
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
            'title' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'title')->ignore($this->id),
            ],
            'description' => 'nullable|string',
            'discount_percentage' => 'required|numeric|min:0|max:100',

            'default_permissions' => 'required|array',
            'default_permissions.*' => 'required|array',
            'default_permissions.*.*' => 'required',

            // // Claims
            // 'default_permissions.claims' => 'required|array',
            // 'default_permissions.claims.create_claim' => 'required|in:0,1',
            // 'default_permissions.claims.update_claim' => 'required|in:0,1',
            // 'default_permissions.claims.show_claim' => 'required|in:0,1',
            // 'default_permissions.claims.assign_claim' => 'required|in:0,1',
            // 'default_permissions.claims.approve_claim' => 'required|in:0,1',
            // 'default_permissions.claims.generate_discharge_letter' => 'required|in:0,1',
            // 'default_permissions.claims.canceled' => 'required|in:0,1',
            // 'default_permissions.claims.import' => 'required|in:0,1',
            // 'default_permissions.claims.export' => 'required|in:0,1',

            // // Claims files
            // 'default_permissions.claims_files' => 'required|array',
            // 'default_permissions.claims_files.create_claims_files' => 'required|in:0,1',
            // 'default_permissions.claims_files.show_claims_files' => 'required|in:0,1',
            // 'default_permissions.claims_files.delete_claims_files' => 'required|in:0,1',

            // // Payment link
            // // 'default_permissions.payment_link' => 'required|array',
            // // 'default_permissions.payment_link.create_payment_link' => 'required|in:0,1',
            // // 'default_permissions.payment_link.update_payment_link' => 'required|in:0,1',
            // // 'default_permissions.payment_link.activate_payment_link' => 'required|in:0,1',
            // // 'default_permissions.payment_link.create_installment_payment_link' => 'required|in:0,1',
            // // 'default_permissions.payment_link.update_installment_payment_link' => 'required|in:0,1',

            // // Settings
            // 'default_permissions.settings' => 'required|array',
            // 'default_permissions.settings.show_settings' => 'required|in:0,1',
            // 'default_permissions.settings.create_settings' => 'required|in:0,1',
            // 'default_permissions.settings.update_settings' => 'required|in:0,1',
            // 'default_permissions.settings.delete_settings' => 'required|in:0,1',

            // // Third parties
            // 'default_permissions.third_parties' => 'required|array',
            // 'default_permissions.third_parties.create_third_parties' => 'required|in:0,1',
            // 'default_permissions.third_parties.update_third_parties' => 'required|in:0,1',
            // 'default_permissions.third_parties.delete_third_parties' => 'required|in:0,1',
            // 'default_permissions.third_parties.activate_third_parties' => 'required|in:0,1',

            // // Users
            // 'default_permissions.users' => 'required|array',
            // 'default_permissions.users.create_users' => 'required|in:0,1',
            // 'default_permissions.users.update_users' => 'required|in:0,1',
            // 'default_permissions.users.show_users' => 'required|in:0,1',
            // // 'default_permissions.users.activate_users' => 'required|in:0,1',
            // // 'default_permissions.users.deactivate_users' => 'required|in:0,1',
            // 'default_permissions.users.delete_users' => 'required|in:0,1',
            // //'default_permissions.users.auto_assign_users' => 'required|in:0,1',

            // // Permissions
            // 'default_permissions.permissions' => 'required|array',
            // //'default_permissions.permissions.create_permissions' => 'required|in:0,1',
            // //'default_permissions.permissions.show_permissions' => 'required|in:0,1',
            // 'default_permissions.permissions.update_permissions' => 'required|in:0,1',

            // // Role
            // 'default_permissions.role' => 'required|array',
            // 'default_permissions.role.create_role' => 'required|in:0,1',
            // 'default_permissions.role.show_role' => 'required|in:0,1',
            // 'default_permissions.role.update_role' => 'required|in:0,1',

            // // Transactions
            // 'default_permissions.transactions' => 'required|array',
            // 'default_permissions.transactions.show_all_transactions' => 'required|in:0,1',

            // // Reports
            // 'default_permissions.reports' => 'required|array',
            // 'default_permissions.reports.show_reports' => 'required|in:0,1',

            // // Payments
            // 'default_permissions.payments' => 'required|array',
            // 'default_permissions.payments.delay_payments' => 'required|in:0,1',
            // 'default_permissions.payments.installment_payments' => 'required|in:0,1',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Role title is required',
            'title.string' => 'Role title must be a string',
            'description.string' => 'Description must be a string',
            'discount_percentage.required' => 'Discount percentage is required',
            'discount_percentage.numeric' => 'Discount must be a number',
            'discount_percentage.max' => 'Discount cannot exceed 100',
            'default_permissions.required' => 'Default permissions are required',
            'default_permissions.*.required' => 'All permission groups are required',
            'default_permissions.*.*.in' => 'Permission value must be 0 or 1',
        ];
    }

    public function getFormattedData(): array
    {
        $validated = $this->validated();

        return [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'discount_percentage' => $validated['discount_percentage'],
            'default_permissions' => $validated['default_permissions'],
        ];
    }
}