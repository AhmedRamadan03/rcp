<?php

namespace App\Enums;

enum SmsProvider: string
{
    case UNIFONIC = 'unifonic';
    case MESGAT = 'mesgat';
    case GIG = 'gig';
    case ALRAJHITAKAFUL = 'alrajhi_takful';

    public function label(): string
    {
        return match($this) {
            self::UNIFONIC => 'unifonic',
            self::MESGAT => 'Mesgat',
            self::GIG => 'gig',
            self::ALRAJHITAKAFUL => 'AlRajhiTakful',
        };
    }

    public function config(): array
    {
        return config("third-party.sms.{$this->value}");
    }
}
