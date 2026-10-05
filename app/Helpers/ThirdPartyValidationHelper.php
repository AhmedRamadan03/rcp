<?php

namespace App\Helpers;

use App\Enums\ThirdPartyType;
use App\Enums\SmsProvider;
use App\Enums\EmailProvider;
use App\Enums\IvrProvider;
use App\Enums\PaymentProvider;
use App\Models\ThirdParty;

class ThirdPartyValidationHelper
{
    /**
     * Build dynamic validation rules for a provider
     */
    public static function buildValidationRules(ThirdPartyType $type, string $vendorLabel): array
    {
        $providerConfig = self::getProviderConfig($type, $vendorLabel);

        if (!$providerConfig) {
            return [];
        }

        $rules = [
            'provider_type' => 'required|string|in:' . implode(',', array_column(ThirdPartyType::cases(), 'value')),
            'vendor_label' => 'required|string',
            'is_active' => 'nullable|boolean',
        ];
        // Build dynamic rules for values JSON field
        foreach ($providerConfig['inputs'] as $input) {
            $rules["values.{$input['name']}"] = $input['validation'];
        }

        return $rules;
    }

    /**
     * Resolve the execution_type for a given provider type + vendor label from config.
     * This is the single source of truth — never trust client input for it.
     */
    public static function resolveExecutionType(ThirdPartyType $type, string $vendorLabel): ?string
    {
        $providerConfig = self::getProviderConfig($type, $vendorLabel);

        return $providerConfig['config'][ThirdParty::CONFIG_EXECUTION_TYPE] ?? null;
    }

    /**
     * Get provider config from type and label
     */
    private static function getProviderConfig(ThirdPartyType $type, string $vendorLabel): ?array
    {
        $providerEnum = self::getProviderEnum($type, $vendorLabel);
        if (!$providerEnum) {
            return null;
        }
        return $providerEnum->config();
    }

    /**
     * Get provider enum from type and label
     */
    private static function getProviderEnum(ThirdPartyType $type, string $vendorLabel): mixed
    {
        return match ($type) {
            ThirdPartyType::SMS => SmsProvider::tryFrom($vendorLabel),
            ThirdPartyType::EMAIL => EmailProvider::tryFrom($vendorLabel),
            ThirdPartyType::PAYMENT => PaymentProvider::tryFrom($vendorLabel),
            ThirdPartyType::IVR => IvrProvider::tryFrom($vendorLabel),
        };
    }
}