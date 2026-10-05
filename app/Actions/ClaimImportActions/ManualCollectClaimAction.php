<?php

namespace App\Actions\ClaimImportActions;

use App\Enums\ClaimStatus;
use App\Enums\LinkSendStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\TransactionType;
use App\Models\Claim;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\PaymentTransaction;
use Carbon\Carbon;



class ManualCollectClaimAction
{
    public function __construct(
        public Claim $claim,
        public ?array $rowData
    ) {}

    public function execute(): void
    {
        $claim = $this->claim;

        (new AcceptClaimAction($claim, $this->rowData))->execute();

        $payment = Payment::where('claim_id', $claim->id)->first();

        $payment_link = PaymentLink::where('payment_id', $payment->id)->first();

        // update payment link
        $payment_link->amount = $claim->amount;
        $payment_link->send_status = LinkSendStatus::PAID->value;
        $payment_link->views_count = $payment_link->views_count + 1;
        $payment_link->last_access = $claim->intimation_date ?? Carbon::today()->format('Y-m-d');
        $payment_link->save();

        // create manual transaction for the payment link
        $transaction = PaymentTransaction::create([
            'payment_id' => $payment->id,
            'link_id' => $payment_link->id,
            'send_status'=> PaymentTransactionStatus::PAID->value,
            'amount' => $payment_link->amount,
            'response' => json_encode([]),
            'payment_type'=> TransactionType::MANUAL->value,
            'collector_id' => $claim->assignee?->to ?? null,
            'created_at' => $claim->intimation_date ?? Carbon::today(),
        ]);

        //update payment
        $payment->payment_status = PaymentStatus::PAID->value;
        $payment->save();


        // update claim
        $claim->status = ClaimStatus::MANUAL_COLLECTED->value;
        $claim->save();

    }
}
