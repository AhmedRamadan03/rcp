<?php

namespace App\Console\Commands;

use App\Enums\Clients;
use App\Enums\IVRs;
use App\Enums\LinkSendStatus;
use App\Enums\MessageTemplates;
use App\Enums\NotificationType;
use App\Enums\Roles;
use App\Exceptions\NotAuthorizeException;
use App\Models\Ivr;
use App\Models\PaymentLink;
use App\Models\User;
use App\Services\MessageService;
use App\Services\NotificationService;
use App\Services\SmsService;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SendLinks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-links';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'send links when due date of the link come';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
        try {
            $links = PaymentLink::with('payment.claim')
                ->where('send_status', LinkSendStatus::NOT_SENT->value)
                ->where('due_date', '<=', Carbon::today()->format('Y-m-d'))
                ->where('is_enabled', 1)
                ->whereHas('payment.claim', function ($query) {
                    $query->where('is_backlog', false);
                })
                ->get();

            //            Log::debug('**************************** SendLinks Command Links to send = '.$links->count().'  ********************');

            $messageService = new MessageService;

            foreach ($links as $payment_link) {

                $links = PaymentLink::where('payment_id', $payment_link->payment_id)
                    ->whereIn('send_status', [LinkSendStatus::NOT_SENT->value, LinkSendStatus::SENT->value, LinkSendStatus::DISPUTE_PAYMENT->value]) //to review
                    ->whereNot('id', $payment_link->id)
                    ->get();

                $user = User::where('role_id', Roles::SUPER_ADMIN->value)->first();
                Auth::setUser($user);

                if ($links->count() != 0) {

                    //                    Log::debug('**************************** SendLinks Command Links to send = '.$payment_link->order.'  ********************');

                    //                     $message = hasMessages() ?
                    //                         "عزيزي العميل؟ نود اشعاركم باستحقاق القسط رقم (" . $payment_link->order . ") نرجو التكرم بسداد المبلغ (" . $payment_link->amount . "  الرجاء الضغط على الرابط التالي ليتم السداد من خلال احدى قنوات الدفع الرسمية المعتمدة لديكم " . $payment_link->url() . "  ، تذكر دائماً أن الخليجية العامة لن تطلب أي أرقام سرية خاصة بكم بأي حال من الأحوال "
                    //                        : "test"
                    //                     ;

                    $message = $messageService->convertTemplate(MessageTemplates::PARTIAL_PAYEMNT->value, $payment_link->id);
                    //
                    $smsService = new SmsService;
                    $smsService->sendSms($payment_link->payment->claim->debtor_phone, $message, $payment_link->payment->claim_id, MessageTemplates::PARTIAL_PAYEMNT->value, $payment_link->id, template_model_id: $payment_link->id);

                    /**
                     * IVR
                     */
                    //                    if (config('app.client_name') === Clients::MIC->value) {
                    //                        if (config('app.have_ivr') === true) {
                    //                            $phone = '+966'.substr($payment_link->payment->claim->debtor_phone, -9);
                    //
                    //                            $mq = (new MaqsamService)->inquiry([
                    //                                'To' => $phone,
                    //                                'InquiryUrl' => 'https://developers.taheiya.sa/maqsam/audios/'.IVRs::toString(IVRs::INSTALLMENT_TIME).'.mp3',
                    ////                                'InquiryUrl' => config('app.url') . '/maqsam/audios/'.IVRs::toString(IVRs::INSTALLMENT_TIME).'.mp3',
                    //                            ]);
                    //
                    //                            MaqsamCallback::create([
                    //                                'InquiryId' => $mq->body(),
                    //                                'type' => IVRs::INSTALLMENT_TIME->value,
                    //                                'payment_link_id' => $payment_link->id,
                    //                            ]);
                    //                        }
                    if (config('app.have_ivr') === true) {
                        try {
                            $response = $smsService->ivr($payment_link, getIVR(IVRs::INSTALLMENT_TIME->value));
                            if (!isset($response['error'])) {
                                IVR::create([
                                    'payment_link_id' => $payment_link->id,
                                    'status' => $response['status'],
                                    'callId' => $response['callId'],
                                    'referenceId' => $response['referenceId'],
                                    'audio_id' => getIVR(IVRs::INSTALLMENT_TIME->value),
                                    'type' => IVRs::INSTALLMENT_TIME->value,
                                ]);
                            }
                        } catch (Exception $ivrException) {
                            Log::error('IVR call failed for payment_link_id=' . $payment_link->id . ': ' . $ivrException->getMessage());
                        }
                    }
                } else {

                    //                     $message = hasMessages() ? "عزيزي العميل ؟ نود اشعاركم بإستحقاق موعد سداد المبلغ المستحق نرجو التكرم بسداد المبلغ " . $payment_link->amount . ")  الرجاء الضغط على الرابط التالي ليتم السداد من خلال احدى قنوات الدفع الرسمية المعتمدة لديكم و سيصلكم رابط الدفع بالتاريخ المحدد " . $payment_link->url() . ")  ، تذكر دائماً أن الخليجية العامة لن تطلب أي أرقام سرية خاصة بكم بأي حال من الأحوال "
                    //                        : "test"
                    //                     ;
                    $message = $messageService->convertTemplate(MessageTemplates::PAYMENT_TIME->value, $payment_link->id);

                    $smsService = new SmsService;
                    $smsService->sendSms($payment_link->payment->claim->debtor_phone, $message, $payment_link->payment->claim_id, MessageTemplates::PAYMENT_TIME->value, $payment_link->id, template_model_id: $payment_link->id);

                    //                    if (config('app.client_name') === Clients::MIC->value) {
                    //                        if (config('app.have_ivr') === true) {
                    //                            $phone = '+966'.substr($payment_link->payment->claim->debtor_phone, -9);
                    //
                    //                            $mq = (new MaqsamService)->inquiry([
                    //                                'To' => $phone,
                    //                                'InquiryUrl' => 'https://developers.taheiya.sa/maqsam/audios/'.IVRs::toString(IVRs::DELAY_TIME).'.mp3',
                    ////                                'InquiryUrl' => config('app.url') . '/maqsam/audios/'.IVRs::toString(IVRs::DELAY_TIME).'.mp3',
                    //                            ]);
                    //
                    //                            MaqsamCallback::create([
                    //                                'InquiryId' => $mq->body(),
                    //                                'type' => IVRs::DELAY_TIME->value,
                    //                                'payment_link_id' => $payment_link->id,
                    //                            ]);
                    //                        }
                    if (config('app.have_ivr') === true) {
                        try {
                            $response = $smsService->ivr($payment_link, getIVR(IVRs::DELAY_TIME->value));
                            if (!isset($response['error'])) {
                                IVR::create([
                                    'payment_link_id' => $payment_link->id,
                                    'status' => $response['status'],
                                    'callId' => $response['callId'],
                                    'referenceId' => $response['referenceId'],
                                    'audio_id' => getIVR(IVRs::DELAY_TIME->value),
                                    'type' => IVRs::DELAY_TIME->value,
                                ]);
                            }
                        } catch (Exception $ivrException) {
                            Log::error('IVR call failed for payment_link_id=' . $payment_link->id . ': ' . $ivrException->getMessage());
                        }
                    }
                }


                $payment_link->send_status = $payment_link->payment->claim->is_msg_skip === true ? LinkSendStatus::SKIPPED->value : LinkSendStatus::SENT->value;
                //
                $payment_link->save();


                $notification = NotificationService::create([
                    'title_ar' => __('notification.notice_for_due_payment', [], 'ar'),
                    'title_en' => __('notification.notice_for_due_payment', [], 'en'),
                    'description_ar' => __('notification.notice_for_due_Payment_for_claim', [], 'ar') . '( ' . $payment_link->payment->claim?->claim_number . ' )  ',
                    'description_en' => __('notification.notice_for_due_Payment_for_claim', [], 'en') . '( ' . $payment_link->payment->claim?->claim_number . ' )  ',
                    'regarding' => NotificationType::GENERAL->value,
                    'user_id' => $payment_link->payment->claim?->assignees()->where('is_active', 1)->latest()->first()?->toUser->id,
                    'to' => json_encode([$payment_link->payment->claim?->assignees()->where('is_active', 1)->latest()->first()?->toUser->id]),
                    'links' => json_encode([
                        [
                            'title_ar' => 'الطلبات المفتوحة',
                            'title_en' => 'Open requests',
                            'url' => 'claims/' . $payment_link->payment->claim_id . '/info'
                        ],
                    ]),
                ]);
            }
        } catch (Exception $e) {
            //            Log::debug("**************************** SendLinks Command Error ******************** \n".$e->getMessage());
            throw new NotAuthorizeException($e->getMessage());
        }
    }
}
