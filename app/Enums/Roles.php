<?php

namespace App\Enums;

enum Roles: int
{
    case SUPER_ADMIN = 1;
    case SUPERVISOR = 2;
    case COLLECTOR = 3;
    case EXTERNAL_OFFICE = 4;
    case LEGAL_DEPARTMENT = 5;
    case TAHEIYA_ADMIN = 6;
    case OTHERS = 7;
    case SENIOR_COLLECTOR = 8;
}
