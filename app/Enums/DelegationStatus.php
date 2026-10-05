<?php

namespace App\Enums;

enum DelegationStatus: string
{
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case NOT_SEEN = 'not seen';
    case EXPIRED = 'expired';
    case IN_PROGRESS = 'in_progress';
    case CANCELLED_MANUALLY = 'cancelled_manually';
    case CANCELLED_AUTOMATICALLY = 'cancelled_automatically';
    case CANCELLED_BY_USER_DELETION = 'cancelled_by_user_deletion';
}
