<?php

namespace App\Enums;

enum MessageTemplates: int
{
    case APPROVE = 1;
    case DELAY_PAYEMNT = 2;
    case PARTIAL_PAYEMNT = 3;
    case TRANSFER_TO_LAWYER = 4;
    case DISCHARGE = 5;
    case VOUCHER = 6;
    case BANK_PAYMENT = 7;
    case OTP = 8;
    case PAYMENT_TIME = 9;

    case PARTIAL_APPROVE = 10;
    case DISCOUNT_APPROVE = 11;

    case APPROVE_DEBTOR = 12;
    case REJECT_DEBTOR = 13;

    case ELM_OTP = 14;

    case DISCOUNT_CANCEL = 15;
    case PAYMENT_FAILED = 16;
}
