<?php

namespace App\Listeners;

use App\Enums\MessageTemplates;
use App\Enums\TwoFactorAuthentication;
use App\Models\Setting;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\MessageService;
use App\Services\SmsService;
use Carbon\Carbon;
use Exception;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Mail;

class SendOtp
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        $this->sendOtp($event->user);
    }

    public function sendOtp($user)
    {
        // Generate An OTP
        if (Setting::twoFactorGeneration()->value == TwoFactorAuthentication::ENABLED->value) {
            $verificationCode = $this->generateOtp($user->email);

            $otp = $verificationCode->otp;
            // Return With OTP
            $smsService = new SmsService;

            try {
                Mail::to($user->email)->send(new \App\Mail\LoginOtp($otp));
            } catch (Exception $e) {

            }

            $message = __('otp.your_otp_to_login_is').$otp;

            try {
                $className = $smsService->returnClassName('sms');

                $messageService = new MessageService;
                $message = $messageService->convertTemplate(MessageTemplates::OTP->value, $verificationCode->id);

                $className->sendSms($user->phone, $message);
            } catch (Exception $e) {
            }
        }
    }

    public function generateOtp($email)
    {
        $user = User::where('email', $email)->first();

        // User Does not Have Any Existing OTP
        $verificationCode = VerificationCode::where('user_id', $user->id)->latest()->first();

        $now = Carbon::now();

        if ($verificationCode && $now->isBefore($verificationCode->expire_at)) {
            return $verificationCode;
        }

        // Create a New OTP
        return VerificationCode::create([
            'user_id' => $user->id,
            'otp' => rand(100000, 999999),
            'expire_at' => Carbon::now()->addMinute(),
        ]);
    }
}
