<?php

namespace App\Actions;

use App\Enums\ClaimStatus;
use App\Enums\LinkSendStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\RequestStatus;
use App\Events\Payment\PaymentSuccess;
use App\Models\Claim;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\StatusChangeRequest;
use App\Services\Payment\CollectorTargetSideEffect;
use App\ThirdParty\PAYMENT\PaymentInterface;
use Carbon\Carbon;
use Exception;

class TransactionEnquiryAction
{
    public function __construct(
        public PaymentInterface $paymentContract,
        public PaymentTransaction $paymentTransaction
    ) {}

    public function execute(): void
    {
        $transaction = $this->paymentContract->transactionEnquiry($this->paymentTransaction);
        $payment_link = $transaction->link;

        $payment = Payment::where('id', $transaction->link->payment_id)
            ->first();

        $claim = Claim::where('id', $payment->claim_id)->first();

        if ($transaction->send_status == PaymentTransactionStatus::PAID->value) {
            $payment->paid_amount = $payment->paid_amount + $payment_link->amount;

            if ($payment->amount <= $payment->paid_amount) {
                $payment->payment_status = PaymentStatus::PAID->value;

                $claim->status = ClaimStatus::DONE->value;

                $claim->save();

                StatusChangeRequest::query()->where('claim_id', $claim->id)
                    ->update([
                        'request_status' => RequestStatus::REJECTED->value,
                    ]);
            } else {
                $claim->status = ClaimStatus::PARTIAL->value;
            }
        }

        // this mean the user pay all the amount

        if ($payment_link->send_status == LinkSendStatus::CANCELED->value && $transaction->send_status == PaymentTransactionStatus::FAILED->value) {
            return;
        }

        if($payment_link->send_status !== LinkSendStatus::PAID->value){
            $payment_link->send_status = $transaction->send_status == PaymentTransactionStatus::PAID->value ? LinkSendStatus::PAID : LinkSendStatus::FAILED;

            $payment_link->views_count = $payment_link->views_count + 1;

            $payment_link->last_access = Carbon::today()->format('Y-m-d');

            $payment_link->save();
        }



        $payment->save();

        CollectorTargetSideEffect::apply(
            $claim,
            (int) $payment_link->amount,
            'transaction_enquiry'
        );

        if ($transaction->send_status == PaymentTransactionStatus::PAID->value) {
            //            event(new PaymentSuccess($transaction));
            $generateLetters = new GenerateLetters($transaction);
            $generateLetters->execute();
        }
    }
}
