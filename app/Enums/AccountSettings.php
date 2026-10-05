<?php

namespace App\Enums;

enum AccountSettings: string
{
    case TWO_FACTOR_AUTH = 'account.settings.two_factor_authentication';
    case EMAIL_SENDING = 'account.settings.email_sending';
}
