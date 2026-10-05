<?php

namespace App\Enums;

enum NotificationStatus: int
{
    case UNREAD = 0;
    case READ = 1;

    public static function color($status)
    {
        return match ($status) {
            self::UNREAD->value => 'bg-gray-600 text-white',
            self::READ->value => 'bg-purple-100 text-black',
        };
    }

    public static function getString(int $status): string
    {
        return match ($status) {
            self::UNREAD->value => __('notification.unread'),
            self::READ->value => __('notification.read'),
        };

    }
}
