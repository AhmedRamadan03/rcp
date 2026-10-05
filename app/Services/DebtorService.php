<?php

namespace App\Services;

use App\Models\Claim;
use App\Models\Payment;
use App\Models\PaymentLink;
use Laravel\Sanctum\PersonalAccessToken;

class DebtorService
{
    public static function issueAccessToken(Payment|PaymentLink $payment): array
    {
        $expiresAt = now()->addMinutes(config('app.client_view.ttl_minutes'));

        $token = $payment->createToken(
            "client_view",
            [],
            $expiresAt
        )->plainTextToken;

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public static function revokeCurrentAccessToken(): void
    {
        self::getAuthenticatedClientRecord()->currentAccessToken()?->delete();
    }
    
    public static function getAuthenticatedClientRecord(): Payment|PaymentLink
    {
        $token = request()->bearerToken() ?? request()->cookie('client_access_token');

        if (!$token) {
            abort(401);
        }

        $accessToken = PersonalAccessToken::findToken($token);

        if (!$accessToken) {
            abort(401);
        }

        return $accessToken->tokenable;
    }

    public static function getAuthenticatedClaim(): Claim
    {
        $payment = self::getAuthenticatedClientRecord();

        return $payment instanceof PaymentLink
            ? $payment->payment->claim
            : $payment->claim;
    }

    public static function hasAccessToClaim(Claim $claim): bool
    {

        return self::getAuthenticatedClaim()->id === $claim->id;
    }


    public static function belongsToAuthenticatedClient(?string $link): bool
    {

        $authClient = self::getAuthenticatedClientRecord();

        if ($link === $authClient->link) {
            return true;
        }

        $authPaymentId = $authClient instanceof Payment
            ? $authClient->id
            : $authClient->payment_id; // this is the payment id of the payment_link model payment() relation

        
        $routePaymentLink = PaymentLink::where('link', $link)->first();
        if ($routePaymentLink && (int) $routePaymentLink->payment_id === (int) $authPaymentId) {
            return true;
        }

        
        $routePayment = Payment::where('link', $link)->first();

        return $routePayment && (int) $routePayment->id === (int) $authPaymentId;
    }
}
