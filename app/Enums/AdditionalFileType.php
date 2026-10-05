<?php

namespace App\Enums;

enum AdditionalFileType: string
{
    case VOUCHER = 'voucher';
    case DISPUTE_PAYMENT = 'dispute_payment';
    case CLAIM_MANUAL_COLLECTED = 'claim_manual_collected';
    case INSTALLMENT_MANUAL_COLLECTED = 'installment_manual_collected';
    case LAW_FIRM = 'law_firm';
    case CLAIM_CLOSED = 'claim_closed';
    case COLLECTED_OUT_OF_SYSTEM = 'collected_out_of_system';
    case CLAIM_REJECTED = 'claim_rejected';
}
