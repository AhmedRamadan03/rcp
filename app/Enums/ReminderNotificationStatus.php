<?php

namespace App\Enums;

enum ReminderNotificationStatus: string
{
    case NOT_FIRED = 'not fired';
    case FIRED = 'fired';
    case OPENED = 'opened';
    case CANCELED = 'canceled';
}
