<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CREDIT_CARD = 'credit card';
    case BANK_TRANSFER = 'bank transfer';
}
