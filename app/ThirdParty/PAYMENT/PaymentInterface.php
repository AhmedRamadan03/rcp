<?php

namespace App\ThirdParty\PAYMENT;

use App\Models\PaymentTransaction;
use Illuminate\Http\RedirectResponse;

interface PaymentInterface
{
    public function pay($payment_link); // : RedirectResponse;

    public function isSuccessful($response): bool;

    public function transactionRef(): string;

    public function transactionEnquiry(PaymentTransaction $payment_transaction): PaymentTransaction;
}
