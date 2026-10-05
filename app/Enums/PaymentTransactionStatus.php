<?php

namespace App\Enums;

enum PaymentTransactionStatus: string
{
    case CREATED = 'created';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';
    case PENDING = 'pending';
}
