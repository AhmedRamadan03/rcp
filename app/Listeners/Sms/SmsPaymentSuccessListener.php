<?php

namespace App\Listeners\Sms;

use App\Events\Payment\PaymentSuccess;
use App\Events\Sms\SendSms as SendSmsEvent;
use App\Services\SettingsService;
use App\Services\SmsService;

class SmsPaymentSuccessListener
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
    public function handle(PaymentSuccess $event): void
    {
        //
        $claim = $event->transaction->payment->claim;

        if (! isListenerActive('PaymentFeedback', 'Sms\SendSms', $claim->client_id)) {
            return;
        }
        // dd();

        $body = SmsTemplateConvertor('PaymentSuccess', $claim);

        $sms_service = new SmsService;
        $sms_service->sendSms($claim->debtor_phone, $body, $claim->id);
    }

    // public function subscribe($events){
    //     $events->listen(
    //         SendSmsEvent::class,
    //         'App\Listeners\SendSms@paymentSuccess'
    //     );

    //     $events->listen(
    //         'App\Events\UserLoggedOut',
    //         'App\Listeners\Sms\SendSms@paymentSuccess'
    //     );
    // }

    // public function paymentSuccess(SendSmsEvent $event){

    //     dd($event);
    //     $isSuccess = collect(SettingsService::getActiveListeners('PaymentFeedback'))
    //         ->where('name' , 'Sms\SendSms')
    //         ->first() > 0;

    //     if(!$isSuccess){
    //         return;
    //     }
    //     $body = SmsTemplateConvertor('PaymentSuccess' , $event->transaction->claim);

    //     $sms_service = new SmsService();
    //     $sms_service->sendSms("0571779327" , $body , $event->transaction->claim_id);

    // }
}
