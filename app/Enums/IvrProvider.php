<?php

namespace App\Enums;

enum IvrProvider: string
{
    case IVR = 'ivr';

    public function label(): string
    {
        return match($this) {
            self::IVR => 'ivr',
        };
    }

    public function config(): array
    {
        return config("third-party.ivr.{$this->value}");
    }
}
