<?php

namespace App\Models;

use App\Enums\ClaimStatus;
use App\Enums\CompanySettings;
use App\Enums\EmailSending;
use App\Enums\LetterGeneration;
use App\Enums\LetterNotification;
use App\Enums\ProofOfDebt;
use App\Enums\TwoFactorAuthentication;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'value' => 'array',
    ];

    public static function paymentReminder()
    {
        return static::firstOrCreate([
            'key' => CompanySettings::CLAIMS_PAYMENT_REMINDER->value,
        ], [
            'value' => [
                ClaimStatus::CLOSED->value => 1,
                ClaimStatus::MANUAL_COLLECTED->value => 1,
                ClaimStatus::CREDITED->value => 1,
                ClaimStatus::FOLLOW_UP->value => 1,
                ClaimStatus::DEFAULT_IN_PAYMENT_BY_SYSTEM->value => 1,
                ClaimStatus::DEFAULT_IN_PAYMENT->value => 1,
                ClaimStatus::LAW_FIRM->value => 1,

            ],
        ]);
    }

    public static function lettersGeneration()
    {
        return static::firstOrCreate([
            'key' => CompanySettings::CLAIMS_LETTERS_GENERATION->value,
        ], [
            'value' => LetterGeneration::MANUAL->value,
        ]);
    }

    public static function lettersGenerationNotification()
    {
        return static::firstOrCreate([
            'key' => CompanySettings::CLAIMS_LETTERS_GENERATION_NOTIFICATION->value,
        ], [
            'value' => LetterNotification::NOT_NOTIFY->value,
        ]);
    }

    public static function paymentReminderClosed()
    {
        $reminder = static::paymentReminder();

        return $reminder->value[ClaimStatus::CLOSED->value];
    }

    public static function paymentReminderManual()
    {
        $reminder = static::paymentReminder();

        return $reminder->value[ClaimStatus::MANUAL_COLLECTED->value];
    }

    public static function paymentReminderCredited()
    {
        $reminder = static::paymentReminder();

        return $reminder->value[ClaimStatus::CREDITED->value];
    }

    public static function paymentReminderFollowUp()
    {
        $reminder = static::paymentReminder();

        return $reminder->value[ClaimStatus::FOLLOW_UP->value];
    }

    public static function paymentReminderStatus($status)
    {
        $reminder = static::paymentReminder();

        return $reminder->value[$status];
    }

    public static function twoFactorGeneration()
    {
        return static::firstOrCreate([
            'key' => CompanySettings::TWO_FACTOR_AUTH->value,
        ], [
            'value' => TwoFactorAuthentication::DISABLED->value,
        ]);
    }

    public static function emailSending()
    {
        return static::firstOrCreate([
            'key' => CompanySettings::EMAIL_SENDING->value,
        ], [
            'value' => EmailSending::DISABLED->value,
        ]);
    }
    public static function hidePhoneNumber()
    {
        return static::firstOrCreate([
            'key' => CompanySettings::HIDE_PHONE_NUMBER->value,
        ], [
            'value' => EmailSending::DISABLED->value,
        ]);
    }
    public static function proofOfDebt()
    {
        return static::firstOrCreate([
            'key' => CompanySettings::PROOF_OF_DEBT->value,
        ], [
            'value' => ProofOfDebt::DISABLED->value,
        ]);
    }

    public static function supervisorEmail()
    {
        return static::firstOrCreate([
            'key' => CompanySettings::SUPERVISOR_EMAIL->value,
        ], [
            'value' => '',
        ]);
    }

}
