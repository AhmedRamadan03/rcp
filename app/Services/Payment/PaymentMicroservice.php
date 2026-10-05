<?php

namespace App\Services\Payment;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;

class PaymentMicroservice
{


    /**
     * Fetch a single payment account by ID
     * Response: {"success":true,"payment_account":[{...}]}
     */
    public static function getPaymentAccountById(int $paymentAccountId): ?array
    {
        $baseUrl = config('payment-microservice.base_url');
        $apiKey = config('payment-microservice.api_key');
        $apiSecret = config('payment-microservice.api_secret');

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'x-api-secret' => $apiSecret,
            ])->timeout(30)->get("{$baseUrl}/admin/payment-accounts/{$paymentAccountId}");
        } catch (ConnectionException $e) {
            throw new Exception('Payment service unavailable, please try again later.');
        }

        if ($response->status() === 404) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();

        if (empty($data['success']) || empty($data['payment_account'])) {
            return null;
        }

        return $data['payment_account'];
    }


    /**
     * Create a charge via the Payment microservice
     *
     * @param float $amount
     * @param int $paymentAccountId
     * @param string|null $customerPhone
     * @param string|null $customerEmail
     * @param string|null $returnUrl
     * @param string|null $webhookUrl
     * @return array{success: bool, charge_id: string, payment_url: string}
     */
    public static function createCharge(
        float $amount,
        int $paymentAccountId,
        string $customerPhone,
        string $customerEmail,
        ?string $returnUrl = null,
        ?string $webhookUrl = null
    ): array {
        $baseUrl = config('payment-microservice.base_url');
        $apiKey = config('payment-microservice.api_key');
        $apiSecret = config('payment-microservice.api_secret');

        $payload = [
            'payment_account_id' => $paymentAccountId,
            'amount' => $amount,
            'customer_phone' => $customerPhone,
            'customer_email' => $customerEmail,
        ];

        if ($returnUrl)
            $payload['return_url'] = $returnUrl;
        if ($webhookUrl)
            $payload['webhook_url'] = $webhookUrl;

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-api-key' => $apiKey,
                'x-api-secret' => $apiSecret,
            ])->timeout(30)->post("{$baseUrl}/payment/create-charge", $payload);
        } catch (ConnectionException $e) {
            throw new Exception('Payment service unavailable, please try again later.');
        }

        if ($response->failed()) {
            throw new Exception('Failed to create payment charge ' . $response['message']);
        }

        $data = $response->json();

        if (empty($data['success'])) {
            throw new Exception($data['message'] ?? 'Payment creation failed');
        }

        return $data;
    }

    /**
     * Check payment status
     *
     * @param string $chargeId
     * @return array{success: bool, transaction: array}
     */
    public static function getStatus(string $chargeId): array
    {
        $baseUrl = config('payment-microservice.base_url');
        $apiKey = config('payment-microservice.api_key');
        $apiSecret = config('payment-microservice.api_secret');

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'x-api-secret' => $apiSecret,
            ])->timeout(60)->get("{$baseUrl}/payment/status/{$chargeId}");
        } catch (ConnectionException $e) {
            throw new Exception('Payment service unavailable, please try again later.');
        }

        if ($response->failed()) {
            throw new Exception('Could not verify payment status');
        }

        return $response->json();
    }


    /**
     * Create a payment account in the microservice
     * Response: {"success":true,"message":"Payment account created","payment_account_id":2}
     */
    public static function storePaymentAccount(array $data): array
    {
        $baseUrl = config('payment-microservice.base_url');
        $apiKey = config('payment-microservice.api_key');
        $apiSecret = config('payment-microservice.api_secret');

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-api-key' => $apiKey,
                'x-api-secret' => $apiSecret,
            ])->timeout(30)->post("{$baseUrl}/admin/payment-accounts", $data);
        } catch (ConnectionException $e) {
            throw new Exception('Payment service unavailable, please try again later.');
        }

        if ($response->failed()) {
            throw new Exception('Failed to create payment account: ' . $response->json('message'));
        }

        return $response->json();
    }


    /**
     * Update a payment account in the microservice
     * Response: {"success":true,"message":"Payment account updated"}
     */
    public static function updatePaymentAccount(int $paymentAccountId, array $data): array
    {
        $baseUrl = config('payment-microservice.base_url');
        $apiKey = config('payment-microservice.api_key');
        $apiSecret = config('payment-microservice.api_secret');

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-api-key' => $apiKey,
                'x-api-secret' => $apiSecret,
            ])->timeout(30)->put("{$baseUrl}/admin/payment-accounts/{$paymentAccountId}", $data);
        } catch (ConnectionException $e) {
            throw new Exception('Payment service unavailable, please try again later.');
        }

        if ($response->failed()) {
            throw new Exception('Failed to update payment account: ' . $response->json('message'));
        }

        return $response->json();
    }


}
