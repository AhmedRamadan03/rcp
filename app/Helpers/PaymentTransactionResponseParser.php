<?php

namespace App\Helpers;

use App\Models\PaymentTransaction;

class PaymentTransactionResponseParser
{
    public static function gatewayResponse(PaymentTransaction $transaction): array
    {
        $data = $transaction->getRawOriginal('response');

        if ($data === null) {
            return [];
        }

        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        if (! is_array($data)) {
            return [];
        }

        if (isset($data['transaction']) && is_array($data['transaction'])) {
            $response = $data['transaction']['response'] ?? $data['transaction'];
        } elseif (isset($data['response'])) {
            $response = $data['response'];
        } else {
            $response = $data;
        }

        if (is_string($response)) {
            $response = json_decode($response, true);
        }

        return is_array($response) ? $response : [];
    }

    public static function paymentId(PaymentTransaction $transaction): ?string
    {
        $response = self::gatewayResponse($transaction);

        // Microservice: gateway_response.fort_id; legacy PayFort: top-level fort_id / PaymentId
        return $response['PaymentId']
            ?? $response['fort_id']
            ?? data_get($response, 'gateway_response.fort_id');
    }

    public static function cardBrand(PaymentTransaction $transaction): ?string
    {
        $response = self::gatewayResponse($transaction);

        return $response['cardBrand']
            ?? $response['payment_option']
            ?? data_get($response, 'gateway_response.payment_option');
    }

    public static function maskedPan(PaymentTransaction $transaction): ?string
    {
        $response = self::gatewayResponse($transaction);

        return $response['maskedPAN']
            ?? $response['card_number']
            ?? data_get($response, 'gateway_response.card_number');
    }

    public static function RRN(PaymentTransaction $transaction): ?string
    {
        $response = self::gatewayResponse($transaction);

        return $response['RRN']
            ?? $response['merchant_reference']
            ?? data_get($response, 'gateway_response.merchant_reference');
    }


    public static function status(PaymentTransaction $transaction): ?string
    {

        return $transaction->send_status;
    }
}
