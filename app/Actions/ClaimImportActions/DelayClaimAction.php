<?php

namespace App\Actions\ClaimImportActions;

use App\Enums\ClaimStatus;
use App\Enums\IVRs;
use App\Enums\LinkSendStatus;
use App\Enums\MessageTemplates;
use App\Enums\PaymentType;
use App\Models\Claim;
use App\Models\Ivr;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Services\ClaimService;
use App\Services\MessageService;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;


class DelayClaimAction
{
    public function __construct(
        public Claim $claim,
        public ?array $rowData
    ) {
    }

    public function execute(): void
    {
        $claim = $this->claim;

        $dateToDelay = Carbon::createFromFormat('Y-m-d', $this->rowData['formatted_date_to_delay']);

        $isDelayInFuture = $dateToDelay->isFuture();

        $intimationDate = $claim->intimation_date
            ? Carbon::createFromFormat('Y-m-d', $claim->intimation_date)
            : Carbon::today();


        (new AcceptClaimAction($claim, $this->rowData))->execute();
        $previous_status = $claim->status;

        $payment = Payment::where('claim_id', $claim->id)->first();
        $full_payment_link = PaymentLink::where('payment_id', $payment->id)->first();

        // cancel the full payment link
        $full_payment_link->send_status = LinkSendStatus::CANCELED->value;
        $full_payment_link->save();

        // create payment link for recovered amount
        if ($claim->recovered_amount) {

            $recovered_amount_payment_link_data = [
                'payment_id' => $payment->id,
                'amount' => $claim->recovered_amount,
                'due_date' => $intimationDate,
                'order' => 1,
                'link' => $full_payment_link->link,
                'type' => PaymentType::INSTALLMENT_PAYMENT->value
            ];

            $recovered_amount_payment_link = (new CreatePaymentLinkAction($recovered_amount_payment_link_data))->execute();

            // mark recovered amount as paid
            $recovered_amount_payment_link->send_status = LinkSendStatus::PAID->value;
            $recovered_amount_payment_link->views_count = $recovered_amount_payment_link->views_count + 1;
            $recovered_amount_payment_link->last_access = $intimationDate;
            $recovered_amount_payment_link->save();
        }



        // create delay payment link
        $delay_payment_link_data = [
            'payment_id' => $payment->id,
            'due_date' => $dateToDelay,
            'amount' => $full_payment_link->amount - $payment->paid_amount,
            'link' => $full_payment_link->link,
            'order' => $full_payment_link->order + 1,
            'type' => PaymentType::DELAY_PAYMENT->value,
        ];

        $delay_payment_link = (new CreatePaymentLinkAction($delay_payment_link_data))->execute();


        $verificationCode = rand(100000, 999999);
        $delay_payment_link->verification_code = $verificationCode;
        $delay_payment_link->save();

        // Only send SMS and trigger IVR if the delay date is in the future
        if ($isDelayInFuture) {
            $message = (new MessageService())->convertTemplate(MessageTemplates::DELAY_PAYEMNT->value, $delay_payment_link->id);
            (new SmsService())->sendSms($claim->debtor_phone, $message, $claim->id, MessageTemplates::DELAY_PAYEMNT->value, $delay_payment_link->id, template_model_id: $delay_payment_link->id);

            if (config('app.have_ivr') === true) {
                $response = (new SmsService())->ivr($delay_payment_link, getIVR(IVRs::DELAY_ACCEPT->value));

                // Skip IVR creation if there was an error
                if (!empty($response) && !isset($response['error'])) {
                    Ivr::create([
                        'payment_link_id' => $delay_payment_link->id,
                        'status' => $response['status'] ?? null,
                        'callId' => $response['callId'] ?? null,
                        'referenceId' => $response['referenceId'] ?? null,
                        'audio_id' => getIVR(IVRs::DELAY_ACCEPT->value),
                        'type' => IVRs::DELAY_ACCEPT->value,
                    ]);
                } else {
                    Log::warning('Skipping IVR creation for delay payment link ID ' . $delay_payment_link->id . '. Response: ' . json_encode($response));
                }
            }
        }

        $claim->status = ClaimStatus::DELAY->value;
        $claim->save();

        (new ClaimService())->writeToClaimStatusHistory($claim->id, $previous_status, $claim->status, $claim->user_id);
    }

}
