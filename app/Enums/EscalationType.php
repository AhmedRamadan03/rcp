<?php

namespace App\Enums;

enum EscalationType: string
{
    case CLAIM_REJECT = 'claim_reject';
    case DEFAULT_ON_PAYMENT = 'default_on_payment';
    case PAYMENT_TIME = 'payment_time';
    case STATUS_REQUEST = 'status_request';
    case DISCOUNT_REQUEST = 'discount_request';
}
