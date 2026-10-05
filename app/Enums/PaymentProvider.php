<?php

namespace App\Enums;

enum PaymentProvider: string
{
    //case URWAY = 'urway';
    case AMAZON_PAYMENT = 'amazon_payment';

    public function label(): string
    {
        return match ($this) {
            //self::URWAY => 'urway',
            self::AMAZON_PAYMENT => 'amazon_payment',
        };
    }

    public function config(): array
    {
        return config("third-party.payment.{$this->value}");
    }

    public function microserviceProviderName(): string
    {
        return match ($this) {
            //self::URWAY => 'urway',
            self::AMAZON_PAYMENT => 'aps',
        };
    }


    public function credentialKeys(): array
    {
        return match ($this) {
            // self::URWAY => [
            //     'terminalId',
            //     'password',
            //     'merchant_key',
            // ],
            self::AMAZON_PAYMENT => [
                'merchant_identifier',
                'access_code',
                'sha_request_phrase',
                'sha_response_phrase',
            ],
        };
    }


    public function buildMicroservicePayload(array $values, string $label): array
    {
        $credentialsData = array_filter(
            $values,
            fn($key) => in_array($key, $this->credentialKeys()),
            ARRAY_FILTER_USE_KEY
        );


        return [
            'provider_name' => $this->microserviceProviderName(),
            'label' => $label,
            'credentials_data' => $credentialsData,
            'currency' => 'SAR',
            'is_test_mode' => (bool) ($values['sandbox_mode'] ?? false),
        ];
    }


    public function buildUpdateMicroservicePayload(array $values): array
    {
        $credentialsData = array_filter(
            $values,
            fn($key) => in_array($key, $this->credentialKeys()),
            ARRAY_FILTER_USE_KEY
        );

        return [
            'credentials_data' => $credentialsData,
            'is_test_mode' => (bool) ($values['sandbox_mode'] ?? false),
        ];
    }


}
