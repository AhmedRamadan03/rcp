<?php

namespace App\Actions\ClaimImportActions;

use App\Enums\ClaimStatus;
use App\Enums\LinkSendStatus;
use App\Enums\MessageTemplates;
use App\Enums\PaymentType;
use App\Models\Claim;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Services\ClaimService;
use App\Services\MessageService;
use App\Services\SmsService;
use Carbon\Carbon;



class PartialFollowUpClaimAction
{
    public function __construct(
        public Claim $claim,
        public ?array $rowData
    ) {
    }

    public function execute(): void
    {

        $claim = $this->claim;

        $installment_date = Carbon::createFromFormat('Y-m-d', $this->rowData['formatted_installment_date']);

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

        // create paid payment link if the claim has recovered_amount
        if (isset($this->rowData['recovered_amount'])) {

            $recovered_amount_payment_link_data = [
                'payment_id' => $payment->id,
                'amount' => $this->rowData['recovered_amount'],
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



        // create installment payment link
        $installment_payment_link_data = [
            'payment_id' => $payment->id,
            'due_date' => $installment_date,
            'amount' => $full_payment_link->amount - $payment->paid_amount,
            'link' => $full_payment_link->link,
            'order' => $full_payment_link->order + 1,
            'type' => PaymentType::INSTALLMENT_PAYMENT->value,
        ];


        $installment_payment_link = (new CreatePaymentLinkAction($installment_payment_link_data))->execute();


        $verificationCode = rand(100000, 999999);
        $installment_payment_link->verification_code = $verificationCode;
        $installment_payment_link->save();


        $claim->status = ClaimStatus::PARTIAL->value;
        $claim->save();

        $message = (new MessageService())->convertTemplate(MessageTemplates::PARTIAL_APPROVE->value, $claim->id);
        (new SmsService())->sendSms($claim->debtor_phone, $message, $claim->id, MessageTemplates::PARTIAL_APPROVE->value, template_model_id: $claim->id);

        (new ClaimService())->writeToClaimStatusHistory($claim->id, $previous_status, $claim->status, $claim->user_id);
    }
}
