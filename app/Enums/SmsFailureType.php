<?php

namespace App\Enums;

use App\Models\Setting;

enum SmsFailureType: string
{
    case BUSINESS = 'business';
    case TECHNICAL = 'technical';

    /**
     * The email address that should receive a failure notification
     * for this failure type.
     */
    public function recipientEmail(): ?string
    {
        return match ($this) {
            self::BUSINESS => Setting::supervisorEmail()->value ?: config('app.admin_email'),
            self::TECHNICAL => config('app.admin_email'),
        };
    }
}
