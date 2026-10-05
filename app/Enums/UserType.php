<?php

namespace App\Enums;

enum UserType: string
{
    case INTERNAL_USER = 'internal_user';
    case EXTERNAL_USER = 'external_user';
}
