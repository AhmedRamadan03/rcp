<?php

namespace App\Listeners\Payment;

use App\Actions\GenerateLetters;
use App\Enums\LetterGeneration;
use App\Enums\PaymentTransactionStatus;
use App\Events\Payment\PaymentSuccess as PaymentSuccessEvent;
use App\Exceptions\NotAuthorizeException;
use App\Models\Setting;
use Illuminate\Contracts\Queue\ShouldQueue;

class PaymentSuccess implements ShouldQueue
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
    public function handle(PaymentSuccessEvent $event): void
    {
        //
        try {

            $letters_generation = Setting::select('value')->where('key', 'claims.letters_generation')->first();
            if ($event->transaction->send_status != PaymentTransactionStatus::PAID->value || $letters_generation->value == LetterGeneration::MANUAL->value) {

                return;
            }

            $generateLetters = new GenerateLetters($event->transaction);
            $generateLetters->execute();
        } catch (NotAuthorizeException $exception) {

        } catch (\Throwable $exception) {
            dd($exception->getMessage());
        }
    }
}
