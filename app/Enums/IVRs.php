<?php

namespace App\Enums;

enum IVRs: string
{
    case APPROVE = 'IVR_APPROVE';
    case DELAY_ACCEPT = 'IVR_DELAY_ACCEPT';
    case PARTIAL_ACCEPT = 'IVR_PARTIAL_ACCEPT';
    case DELAY_TIME = 'IVR_DELAY_TIME';
    case INSTALLMENT_TIME = 'IVR_INSTALLMENT_TIME';

    case DEBTOR_REQUEST_ACCEPT = 'IVR_DEBTOR_REQUEST_ACCEPT';
    case DEBTOR_REQUEST_REJECT = 'IVR_DEBTOR_REQUEST_REJECT';

    public static function toString(self $value): string
    {
        return \Str::lower($value->value);
    }
}
