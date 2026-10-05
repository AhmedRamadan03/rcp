<?php

namespace App\Enums;

enum LinkSendStatus: string
{
    case NOT_SENT = 'not sent';
    case SENT = 'sent';
    case PAID = 'paid';
    case CANCELED = 'canceled';
    case EXPIRED = 'expired';
    case FAILED = 'failed';
    case DISPUTE_PAYMENT= 'dispute payment';
    case SKIPPED = 'skipped';
    case PENDING = 'pending';
}
