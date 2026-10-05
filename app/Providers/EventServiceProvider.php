<?php

namespace App\Providers;

use App\Events\FailedLoginAttemptEvent;
use App\Events\Payment\PaymentSuccess as PaymentSuccessEvent;
use App\Events\ResetPasswordEvent;
use App\Listeners\Payment\PaymentSuccess as PaymentSuccessListener;
use App\Listeners\SendFailedLoginEmail;
use App\Listeners\SendOtp;
use App\Listeners\SendResetPasswordEmail;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        Login::class => [
            SendOtp::class,
        ],
        PaymentSuccessEvent::class => [
            PaymentSuccessListener::class,
        ],
        FailedLoginAttemptEvent::class => [
            SendFailedLoginEmail::class,
        ],
        ResetPasswordEvent::class => [
            SendResetPasswordEmail::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
