<?php

namespace App\Enums;

enum ThirdPartyType: string
{
    case SMS = 'sms';
    case EMAIL = 'email';
    case PAYMENT = 'payment';
    case IVR = 'ivr';

    public function label(): string
    {
        return match($this) {
            self::SMS => 'SMS Service',
            self::EMAIL => 'Email Service',
            self::PAYMENT => 'Payment Gateway',
            self::IVR => 'ivr',
        };
    }

    public function providers(): array
    {
        return match($this) {
            self::SMS => SmsProvider::cases(),
            self::EMAIL => EmailProvider::cases(),
            self::PAYMENT => PaymentProvider::cases(),
            self::IVR => IvrProvider::cases(),
        };
    }
}
