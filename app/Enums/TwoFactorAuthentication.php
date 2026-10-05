<?php

namespace App\Enums;

enum TwoFactorAuthentication: int
{
    case DISABLED = 0;
    case ENABLED = 1;
}
