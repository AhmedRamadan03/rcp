<?php

namespace App\Listeners;


use App\Events\FailedLoginAttemptEvent;
use App\Mail\FailedLoginMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class SendFailedLoginEmail
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
    public function handle(FailedLoginAttemptEvent $event)
    {
        
        $token = Password::createToken($event->user);

        $resetUrl = config('app.frontend_url')
            . '/changing-password'
            . '?token=' . $token
            . '&email=' . urlencode($event->user->email);

        Mail::to($event->user->email)
            ->queue(new FailedLoginMail($event->user, $resetUrl));
    }
}
