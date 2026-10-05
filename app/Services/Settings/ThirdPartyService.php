<?php

namespace App\Services\Settings;

use App\Enums\ThirdPartyType;
use App\Enums\SmsProvider;
use App\Enums\EmailProvider;
use App\Enums\PaymentProvider;
use App\Helpers\ThirdPartyValidationHelper;
use App\Models\ThirdParty;
use App\Services\Payment\PaymentMicroservice;
use Illuminate\Support\Str;



class ThirdPartyService
{

    public function getProvidersByType(ThirdPartyType $type)
    {
        $providers = [];
        foreach ($type->providers() as $provider) {
            $config = $provider->config();
            $providers[] = [
                'vendor_name' => $config['vendor_name'],
                'vendor_label' => $config['vendor_label'],
                'config' => $config['config'],
                'inputs' => array_map(
                    fn($input) => array_diff_key($input, ['sensitive' => true]), // Remove sensitive keys from the input response
                    $config['inputs']
                ),
            ];
        }

        return $providers;
    }



    public function storeThirdParty(array $data): ThirdParty
    {
        $clientId = $data['client_id'];
        $providerType = $data['provider_type'];

        $isFirstThirdParty = $this->isFirstThirdPartyForType($clientId, $providerType);
        $isActive = $isFirstThirdParty ? true : ($data['is_active'] ?? false);


        if ($isActive) {
            ThirdParty::where('client_id', $data['client_id'])
                ->where('type', $data['provider_type'])
                ->update(['is_active' => false]);
        }

        $values = $data['values'];

        $executionType = ThirdPartyValidationHelper::resolveExecutionType(
            ThirdPartyType::from($data['provider_type']),
            $data['vendor_label']
        );

        // handle payment providers with microservice
        if ($providerType == ThirdPartyType::PAYMENT->value) {
            $paymentProvider = PaymentProvider::tryFrom($data['vendor_label']);

            if (!$paymentProvider) {
                throw new \Exception("Unsupported payment vendor: {$data['vendor_label']}");
            }

            $paymentAccountLabel = $data['vendor_label'] . ':' . Str::uuid();

            $microserviceResponse = PaymentMicroservice::storePaymentAccount(
                $paymentProvider->buildMicroservicePayload($values, $paymentAccountLabel)
            );


            if (empty($microserviceResponse['success']) || empty($microserviceResponse['payment_account_id'])) {
                throw new \Exception($microserviceResponse['message'] ?? 'Failed to register payment account.');
            }

            $values['payment_account_id'] = $microserviceResponse['payment_account_id'];
            $values['payment_account_label'] = $paymentAccountLabel;

        }

        return ThirdParty::create([
            'client_id' => $data['client_id'],
            'type' => $data['provider_type'],
            'vendor_name' => $data['vendor_name'],
            'vendor_label' => $data['vendor_label'],
            'is_active' => $isActive,
            'values' => $values,
            'config' => [ThirdParty::CONFIG_EXECUTION_TYPE => $executionType],
        ]);
    }


    /**
     * Update an existing third-party provider
     */
    public function updateThirdParty(ThirdParty $thirdParty, array $data): ThirdParty
    {
        $providerType = $data['provider_type'] ?? $thirdParty->type;
        $values = $data['values'] ?? $thirdParty->values;

        $executionType = ThirdPartyValidationHelper::resolveExecutionType(
            ThirdPartyType::from($data['provider_type']),
            $data['vendor_label']
        );

        if ($providerType === ThirdPartyType::PAYMENT->value) {
            $paymentProvider = PaymentProvider::tryFrom($data['vendor_label'] ?? $thirdParty->vendor_label);

            if (!$paymentProvider) {
                throw new \Exception("Unsupported payment vendor: {$thirdParty->vendor_label}");
            }

            // Get payment_account_id from stored values using the UUID label as proof of ownership
            $paymentAccountId = $thirdParty->values['payment_account_id'] ?? null;
            $paymentAccountLabel = $thirdParty->values['payment_account_label'] ?? null;

            if (!$paymentAccountId || !$paymentAccountLabel) {
                throw new \Exception('Payment account reference missing. Cannot update microservice.');
            }

            // Verify local record is in sync with the microservice
            $verification = $thirdParty->verifyWithMicroservice();

            if (!$verification['is_synced']) {
                throw new \Exception('Payment account not configured with payment service ' . $verification['reason']);
            }

            // microservice update
            PaymentMicroservice::updatePaymentAccount(
                $paymentAccountId,
                $paymentProvider->buildUpdateMicroservicePayload($values)
            );

            // Preserve payment_account_id and payment_account_label in values
            $values['payment_account_id'] = $paymentAccountId;
            $values['payment_account_label'] = $paymentAccountLabel;

        }

        $thirdParty->update([
            'type' => $providerType,
            'vendor_name' => $data['vendor_name'] ?? $thirdParty->vendor_name,
            'vendor_label' => $data['vendor_label'] ?? $thirdParty->vendor_label,
            'is_active' => $thirdParty->is_active,
            'values' => $values,
            'config' => array_merge($thirdParty->config ?? [], [ThirdParty::CONFIG_EXECUTION_TYPE => $executionType]),
        ]);

        return $thirdParty->fresh();
    }


    /**
     * Delete a third-party provider
     */
    public function deleteThirdParty(ThirdParty $thirdParty): bool
    {
        $isOptionalTypeToDelete = in_array(
            $thirdParty->type,
            [
                ThirdPartyType::PAYMENT->value,
                ThirdPartyType::IVR->value,
            ]
        );

        if (!$isOptionalTypeToDelete) {
            if ($thirdParty->is_active) {
                throw new \Exception("Cannot delete the active {$thirdParty->type} provider. You must set another provider as active before deleting this one.");
            }
        }

        

        $isLastOfType = ThirdParty::where('client_id', $thirdParty->client_id)
            ->where('type', $thirdParty->type)
            ->count() === 1;

        if ($isLastOfType && !$isOptionalTypeToDelete) {
            throw new \Exception(
                "Cannot delete the last {$thirdParty->type} provider. At least one provider of each type must exist."
            );
        }



        return $thirdParty->delete();
    }


    /**
     * Set a third-party as default (active) for its type
     */
    public function setDefaultThirdParty(ThirdParty $thirdParty): ThirdParty
    {
        ThirdParty::where('client_id', $thirdParty->client_id)
            ->where('type', $thirdParty->type)
            ->where('id', '!=', $thirdParty->id)
            ->update(['is_active' => false]);

        $thirdParty->update(['is_active' => true]);

        return $thirdParty->fresh();
    }


    /**
     * Toggle a third-party 
     */
    public function toggleThirdPartyActive(ThirdParty $thirdParty): ThirdParty
    {
        $becomingActive = !$thirdParty->is_active;

        if ($becomingActive) {
            $hasActiveProvider = ThirdParty::where('client_id', $thirdParty->client_id)
                ->where('type', $thirdParty->type)
                ->where('is_active', true)
                ->where('id', '!=', $thirdParty->id)
                ->exists();

            if ($hasActiveProvider) {
                throw new \Exception(
                    "Cannot activate this {$thirdParty->type} provider. Another provider is already active. Deactivate it first."
                );
            }
        }

        $thirdParty->update(['is_active' => $becomingActive]);

        return $thirdParty->fresh();
    }

    public function isFirstThirdPartyForType(int $clientId, string $type): bool
    {
        return ThirdParty::where('client_id', $clientId)
            ->where('type', $type)
            ->count() === 0;
    }
}
