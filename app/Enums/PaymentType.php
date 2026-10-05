<?php

namespace App\Enums;

enum PaymentType: string
{
    // case TRACKED = 'tracked'; // (should be in claim state)
    case FULL_PAYMENT = 'full_payment'; //  waiting for full payment calculated
    case DELAY_PAYMENT = 'delay_payment';

    case INSTALLMENT_PAYMENT = 'installment_payment';
}
