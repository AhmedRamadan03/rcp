<?php

namespace App\Enums;

enum SmsStatus: string
{
    case SENDED = 'sended';
    case NO_SENDED = 'not sended';
    case DELIVERED = 'delivered';
    case NO_DELIVERED = 'not delivered';
    case SKIPPED = 'skipped';
    case PENDING = 'pending';
    case PENDING_TO_HAVE_PROVIDER = 'pending_to_have_provider';
    case SUCCESS = 'Success';

    public static function color($status)
    {
        return match ($status) {
            'not delivered' => 'bg-purple-50',
            'not sended' => 'bg-red-50',
            'sended' => 'bg-green-50',
            'delivered' => 'bg-purple-50'
        };
    }
}
