<?php

namespace App\Enums;

enum CallStatus: string
{
    case FIRED = 'fired';
    case NO_FIRED = 'not fired';
    case ANSWERED = 'answered';
    case NO_ANSWERED = 'not answer';
}
