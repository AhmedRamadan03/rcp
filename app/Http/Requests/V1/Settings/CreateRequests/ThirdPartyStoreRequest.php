<?php

namespace App\Http\Requests\V1\Settings\CreateRequests;

use Illuminate\Foundation\Http\FormRequest;
use App\Helpers\ThirdPartyValidationHelper;
use App\Enums\ThirdPartyType;
use App\Enums\SmsProvider;
use App\Enums\EmailProvider;
use App\Enums\IvrProvider;
use App\Enums\PaymentProvider;

class ThirdPartyStoreRequest extends FormRequest
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
        $provider_type = $this->input('provider_type');
        $vendorLabel = $this->input('vendor_label');
        $vendorName = $this->input('vendor_name');

        // Base rules that always apply
        $baseRules = [
            'provider_type' => 'required|string|in:' . implode(',', array_column(ThirdPartyType::cases(), 'value')),
            'vendor_label' => 'required|string',
            'vendor_name' => 'required|string',
            'is_active' => 'nullable|boolean',
            'values' => 'required|array',
        ];

        if (!$provider_type) {
            return $baseRules;
        }
        // Validate that vendor_label is valid for this provider type
        $baseRules['vendor_label'] = $this->getVendorLabelValidation($provider_type);

        // If vendor_label is not provided or invalid, return base rules
        if (!$vendorLabel || !$this->isValidVendorLabel($provider_type, $vendorLabel)) {
            return $baseRules;
        }

        // Get the expected vendor name for this vendor label
        $expectedVendorName = $this->getExpectedVendorName($provider_type, $vendorLabel);
        
        // Validate vendor_name matches the provider's configured vendor name
        $baseRules['vendor_name'] = 'required|string|in:' . $expectedVendorName;

        try {
            $typeEnum = ThirdPartyType::tryFrom($provider_type);

            if (!$typeEnum) {
                return $baseRules;
            }

            // Get dynamic validation rules from service
            $dynamicRules = ThirdPartyValidationHelper::buildValidationRules($typeEnum, $vendorLabel);

            // Merge base rules with dynamic rules
            return array_merge($baseRules, $dynamicRules);
        } catch (\Exception $e) {
            return $baseRules;
        }
    }

    /**
     * Get vendor label validation rule based on provider type
     */
    private function getVendorLabelValidation(string $provider_type): string
    {
        $validLabels = match($provider_type) {
            'sms' => implode(',', array_column(SmsProvider::cases(), 'value')),
            'email' => implode(',', array_column(EmailProvider::cases(), 'value')),
            'payment' => implode(',', array_column(PaymentProvider::cases(), 'value')),
            'ivr' => implode(',', array_column(IvrProvider::cases(), 'value')),
            default => ''
        };

        return "required|string|in:$validLabels";
    }

    /**
     * Check if vendor label is valid for the given provider type
     */
    private function isValidVendorLabel(string $provider_type, string $vendorLabel): bool
    {
        return match($provider_type) {
            'sms' => SmsProvider::tryFrom($vendorLabel) !== null,
            'email' => EmailProvider::tryFrom($vendorLabel) !== null,
            'payment' => PaymentProvider::tryFrom($vendorLabel) !== null,
            'ivr' => IvrProvider::tryFrom($vendorLabel) !== null,
            default => false,
        };
    }

    /**
     * Get the expected vendor name for a specific vendor label
     */
    private function getExpectedVendorName(string $provider_type, string $vendorLabel): string
    {
        $config = config("third-party.$provider_type.$vendorLabel");
        
        if (!$config) {
            return '';
        }

        return $config['vendor_name'];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'provider_type.required' => 'Service type is required',
            'provider_type.in' => 'Invalid service type. Must be one of: sms, email, payment',
            'vendor_label.required' => 'Provider is required',
            'vendor_label.in' => 'Invalid provider for the selected service type. Check available providers endpoint.',
            'vendor_name.required' => 'Vendor name is required',
            'vendor_name.in' => 'Vendor name must match the selected provider. Expected: ' . $this->getExpectedVendorName($this->input('provider_type'), $this->input('vendor_label')),
            'values.required' => 'Values are required',
            'values.array' => 'Values must be an array',
            'values.*.required' => 'This :attribute field is required',
        ];
    }
}
