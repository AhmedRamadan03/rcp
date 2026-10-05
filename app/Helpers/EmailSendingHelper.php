<?php

namespace App\Helpers;

use App\Enums\EmailSending;
use App\Models\Setting;
use App\Models\UserSetting;

class EmailSendingHelper
{
    /**
     * Check if email sending is enabled at the company level.
     */
    public static function isCompanyEmailEnabled(): bool
    {
        return Setting::emailSending()->value === EmailSending::ENABLED->value;
    }

    /**
     * Check if email sending is enabled at the user level.
     */
    public static function canUserSendEmail(int $userId): bool
    {
        return UserSetting::emailSendingSetting($userId)->value === EmailSending::ENABLED->value;
    }

    /**
     * Check if email sending is enabled at the user and company level.
     */
    public static function canSendEmail(int $userId): bool
    {
        return  static::isCompanyEmailEnabled() && static::canUserSendEmail($userId);
    }
    
}