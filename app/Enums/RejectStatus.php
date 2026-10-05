<?php

namespace App\Enums;

enum RejectStatus: string
{
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case NOT_SEEN = 'not seen';
}
