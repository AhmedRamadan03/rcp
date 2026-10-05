<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case EXPIRED = 'expired';
    case DELETED = 'deleted';
    case EXCEPTION = 'exception';
    case TRIAL = 'trial';
    case REJECTED = 'rejected';
}
