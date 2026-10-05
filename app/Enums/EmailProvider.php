<?php

namespace App\Enums;

enum EmailProvider: string
{
    case GMAIL = 'gmail';
    case HOTMAIL = 'hotmail';

    public function label(): string
    {
        return match($this) {
            self::GMAIL => 'Gmail',
            self::HOTMAIL => 'Hotmail',
        };
    }

    public function config(): array
    {
        return config("third-party.email.{$this->value}");
    }
}
