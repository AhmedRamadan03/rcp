<?php

namespace App\Enums;

enum NotificationType: string
{
    case ALL = 'all';
    case GENERAL = 'general';
    case NEW_CLAIM_CREATED = 'new_claim_created';
    case CLAIM_ASSIGNED = 'claim_assigned';
    case CLAIM_IMPORT = 'claim_import';
    case EXCEL_BULK_ASSIGN = 'excel_bulk_assign';
    case DELEGATIONS = 'delegations';

    case REQUESTS_CREATION = 'requests_creation';
    case REQUESTS_ACCEPT = 'requests_accept';
    case REQUESTS_REJECT = 'requests_reject';
    case LAW = 'law';

    case CLIENT_REQUEST = 'client_request';
    case PERMISSION_REQUEST = 'permission_request';
    case PAID_INSTALLMENT = 'paid_installment';
    case DISCHARGE_LETTER = 'discharge_letter';
    case VOUCHER_LETTER = 'voucher_letter';
    case FAILD_CLAIM_SIGNIN = 'failed_claim_signin';
    case EXPORT = 'export';
    case BILLED_UPDATE_CLAIM = 'billed_update_claim';

    public static function color($status)
    {
        return match ($status) {
            'all' => '',
            'general' => 'green',
            'new_claim_created' => 'blue',
            'claim_assigned' => 'indigo',
            'delegations' => 'orange',
            'requests_creation' => 'pink',
            'requests_accept' => 'blue',
            'requests_reject' => 'orange',
            'law' => 'pink',
        };
    }
}
