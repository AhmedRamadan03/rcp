<?php

namespace App\Enums;

enum PermissionRequestsStatus: string
{
    case GENERATE_DISCHARGE_LETTER = 'generate_discharge_letter';
    case SEND_DISCHARGE_LETTER = 'send_discharge_letter';

    case GENERATE_VOUCHER_LETTER = 'generate_voucher_letter';
    case SEND_VOUCHER_LETTER = 'send_voucher_letter';
    case GO_BACK_TO_ORIGINAL_STATUS = 'go_back_to_original_status';
}
