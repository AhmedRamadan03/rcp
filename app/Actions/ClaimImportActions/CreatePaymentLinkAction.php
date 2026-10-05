<?php

namespace App\Actions\ClaimImportActions;

use App\Enums\PaymentType;
use App\Helpers\PaymentLinkHelper;
use App\Models\PaymentLink;
use Exception;

class CreatePaymentLinkAction
{
    public function __construct(public array $paymentLinkData){}

    public function execute():PaymentLink
    {
        try {
            return PaymentLinkHelper::createUnique(
                paymentId: $this->paymentLinkData['payment_id'],
                amount: $this->paymentLinkData['amount'],
                dueDate: $this->paymentLinkData['due_date'],
                type: $this->paymentLinkData['type'] ?? PaymentType::FULL_PAYMENT->value,
                order: $this->paymentLinkData['order']
            );
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage());
        }
    }
}
