<?php

namespace App\Enums;

enum TargetStatus: string
{
    case PENDING = 'pending';
    case ACHIEVED = 'achieved';
    case MISSED = 'missed';
}
