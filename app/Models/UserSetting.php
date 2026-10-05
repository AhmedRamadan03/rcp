<?php

namespace App\Models;

use App\Enums\{EmailSending,TwoFactorAuthentication,AccountSettings};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'value' => 'array',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**  
     * Get or create a setting for a specific user with a default value.  
    **/ 

    public static function getOrCreate($userId, $key, $defaultValue)
    {
        return static::firstOrCreate(
            ['user_id' => $userId, 'key' => $key],
            ['value' => $defaultValue]
        );
    }

    public static function twoFactorSetting($userId)
    {
        return static::getOrCreate(
            $userId,
            AccountSettings::TWO_FACTOR_AUTH->value,
            TwoFactorAuthentication::DISABLED->value
        );
    }

    public static function emailSendingSetting($userId)
    {
        
        return static::getOrCreate(
            $userId,
            AccountSettings::EMAIL_SENDING->value,
            EmailSending::DISABLED->value
        );
    }

}
