<?php

namespace App\Enums;

enum DebtorType: string
{
    case INSURED = 'insured';
    case THIRD_PARTY = 'third party';
}
