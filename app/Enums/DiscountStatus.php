<?php

namespace App\Enums;

enum DiscountStatus: string
{
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
