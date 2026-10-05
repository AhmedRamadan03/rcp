<?php

namespace App\Services\Payment;

use App\Enums\LinkSendStatus;
use App\Enums\PaymentType;
use App\Enums\ThirdPartyType;
use App\Enums\TransactionType;
use App\Models\PaymentLink;
use App\Models\PaymentTransaction;
use App\Models\ThirdParty;
use Exception;



class PaymentServiceGateway
{

    public static function chargePayment(PaymentLink $paymentLink, ThirdParty $paymentAccount, $returnUrl)
    {
        $paymentModel = $paymentLink->payment;
        $collector_id = $paymentLink->payment?->claim?->assignee?->to ?? null;
        $customerPhone = $paymentModel->claim?->debtor_phone ?? '+966920029028';
        $customerEmail = config('app.admin_email', 'info@taheiya.sa');
        $webhookUrl = url('/api/v1/payments/webhook');

        if ($paymentLink->type === PaymentType::FULL_PAYMENT->value) {
            // exclude pending links amounts from the remaining balance
            $pendingAmount = $paymentModel->links()
                ->where('send_status', LinkSendStatus::PENDING->value)
                ->sum('amount');

            $amount = $paymentModel->amount - $paymentModel->paid_amount - $pendingAmount;

        } else {
            $amount = $paymentLink->amount;
        }


        $chargeResponse = PaymentMicroservice::createCharge(
            $amount,
            $paymentAccount->values['payment_account_id'],
            (String) $customerPhone,
            $customerEmail,
            $returnUrl,
            $webhookUrl
        );

        if ($chargeResponse['success']) {
            if ($paymentLink->type === PaymentType::FULL_PAYMENT->value) {
                $paymentLink->amount = $amount;
                $paymentLink->save();
            }

            // initiate payment transaction
            $payment_transaction = PaymentTransaction::initiatePayment(
                $paymentLink,
                $chargeResponse['charge_id'],
                $amount,
                $collector_id,
                TransactionType::ONLINE->value
            );
            // update payment transaction with microservice response
            $payment_transaction->saveRequest(json_encode($chargeResponse));
            $payment_transaction->save();
        }

        return $chargeResponse;

    }

    /**
     * Get the active payment account and verify it's in sync with the microservice
     */
    public static function getActivePaymentAccount(): ?ThirdParty
    {
        $thirdParty = ThirdParty::where('type', ThirdPartyType::PAYMENT->value)
            ->where('is_active', true)
            ->first();

        if (!$thirdParty) {
            throw new Exception('No active payment account configured.');
            
        }

        $verification = $thirdParty->verifyWithMicroservice();

        if (!$verification['is_synced']) {
            throw new Exception('Payment account not configured with payment service ' . $verification['reason']);
        }

        return $thirdParty;
    }


}
