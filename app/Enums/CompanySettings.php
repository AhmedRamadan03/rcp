<?php

namespace App\Enums;

enum CompanySettings: string
{
    case TWO_FACTOR_AUTH = 'settings.two_factor_authentication';
    case EMAIL_SENDING = 'settings.email_sending';
    case PROOF_OF_DEBT = 'settings.proof_of_debt';
    case HIDE_PHONE_NUMBER = 'settings.hide_phone_number';
    case SUPERVISOR_EMAIL = 'settings.supervisor_email';
    case CLAIMS_PAYMENT_REMINDER = 'claims.payment_reminder';
    case CLAIMS_LETTERS_GENERATION = 'claims.letters_generation';
    case CLAIMS_LETTERS_GENERATION_NOTIFICATION = 'claims.letters_generation_notification';
}
