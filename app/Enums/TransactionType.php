<?php

namespace App\Enums;

enum TransactionType: string
{
    case MANUAL = 'manual';
    case MADA = 'mada';
    case SADAD = 'sadad';
    case ONLINE = 'online';

}
