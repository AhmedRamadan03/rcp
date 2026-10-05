<?php

namespace App\Enums;

enum RequestStatus: string
{
    case REGISTERED = 'registered';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
}
