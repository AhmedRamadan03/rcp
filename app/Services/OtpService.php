<?php

namespace App\Services;

use App\Models\VerificationCode;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class OtpService
{

    // How long each OTP is valid (in minutes)
    const TTL_MINUTES = 5;


    /**
     * Generate valid OTP for any model + purpose.
     */
    public function generate(Model $otpable, string $purpose, string $phone): VerificationCode
    {
        // Expire any existing OTPs for this model + purpose
        VerificationCode::where('otpable_type', get_class($otpable))
            ->where('otpable_id', $otpable->getKey())
            ->forPurpose($purpose)
            ->whereNull('verified_at')
            ->update(['expire_at' => Carbon::now()]);

        return VerificationCode::create([
            'otpable_type' => get_class($otpable),
            'otpable_id' => $otpable->getKey(),
            'otp' => (string) rand(100000, 999999),
            'purpose' => $purpose,
            'phone' => $phone,
            'expire_at' => Carbon::now()->addMinutes(self::TTL_MINUTES),
        ]);
    }

    /**
     * Verify an OTP. Returns the code on success, null on failure.
     */
    public function verify(
        Model $otpable,
        string $purpose,
        string $otp,
        string $phone
    ): ?VerificationCode {
        $code = VerificationCode::where('otpable_type', get_class($otpable))
            ->where('otpable_id', $otpable->getKey())
            ->forPurpose($purpose)
            ->where('otp', $otp)
            ->where('phone', $phone)
            ->valid() // not verified + not expired
            ->latest()
            ->first();

        if (!$code) {
            return null; // wrong OTP or expired
        }

        $code->markVerified();

        return $code;
    }

    /**
     * Check whether a model has a currently-verified OTP for a purpose.
     */
    public function isVerified(Model $otpable, string $purpose): bool
    {
        return VerificationCode::where('otpable_type', get_class($otpable))
            ->where('otpable_id', $otpable->getKey())
            ->forPurpose($purpose)
            ->whereNotNull('verified_at')
            ->exists();
    }

}
