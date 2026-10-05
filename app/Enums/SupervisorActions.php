<?php

namespace App\Enums;

enum SupervisorActions: string
{
    case PENDING = 'pending';
    case SEND_IT_BACK = 'send_it_back';
    case SEND_IT_LEGAL = 'send_it_legal';
    case SEND_IT_OTHER_COLLECTOR_OR_EXTERNAL_OFFICE = 'send_it_other_collector_office';
}
