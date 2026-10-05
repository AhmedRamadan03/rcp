<?php

namespace App\Actions;

use App\Enums\ClaimStatus;
use App\Enums\LinkSendStatus;
use App\Enums\MessageTemplates;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PaymentType;
use App\Models\Claim;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\PaymentTransaction;
use App\Services\MessageService;
use App\Services\Payment\CollectorTargetSideEffect;
use App\Services\Payment\PaymentMicroservice;
use App\Services\SmsService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class TransactionStatusEnquiryAction
{
    public function __construct(
        public $chargeId
    ) {
    }

    public function execute(): void
    {
        $charge_id = $this->chargeId;

        $exists = PaymentTransaction::where('transaction_ref', $charge_id)->exists();
        if (!$exists) {
            return;
        }

        // Network call to the microservice — kept OUTSIDE the row lock
        // so we don't hold a DB lock while waiting on external I/O.
        $result = PaymentMicroservice::getStatus($charge_id);

        if (!$result['success'] || empty($result['transaction'])) {
            return;
        }

        $status = $result['transaction']['status'] ?? null;
        $is_successful = $status === 'success';
        $is_pending = $status === 'pending';
        $is_cancelled = $status === 'cancelled';
        $is_failed = !$is_successful && !$is_pending && !$is_cancelled;

        $transactionForLetters = null;
        $collectorTargetClaimId = null;
        $collectorTargetAmount = null;

        DB::transaction(function () use ($charge_id, $result, $is_successful, $is_pending, $is_cancelled, $is_failed, &$transactionForLetters, &$collectorTargetClaimId, &$collectorTargetAmount) {
            // Lock the transaction row. A concurrent caller (webhook vs
            // callback) blocks here until this transaction commits, then
            // re-reads the row with the now-updated status.
            $payment_transaction = PaymentTransaction::where('transaction_ref', $charge_id)
                ->lockForUpdate()
                ->first();

            if (!$payment_transaction) {
                return;
            }

            // Idempotency guard — safe now, we hold the row lock.
            if ($payment_transaction->send_status === PaymentTransactionStatus::PAID->value) {
                return;
            }

            // Fresh query on the FK column (link_id) instead of the ->link
            // relation — avoids picking up a stale, already-hydrated
            // instance that would bypass the lock.
            $payment_link = PaymentLink::where('id', $payment_transaction->link_id)
                ->lockForUpdate()
                ->first();

            if (!$payment_link) {
                return;
            }

            // check if it was canceled and failed
            if ($payment_link->send_status == LinkSendStatus::CANCELED->value
                && $payment_transaction->send_status == PaymentTransactionStatus::FAILED->value) {
                return;
            }

            // update transaction status
            $new_status = match (true) {
                $is_successful => PaymentTransactionStatus::PAID->value,
                $is_pending => PaymentTransactionStatus::PENDING->value,
                $is_cancelled => PaymentTransactionStatus::CANCELLED->value,
                default => PaymentTransactionStatus::FAILED->value,
            };

            // If the transaction is already sitting at the status we're
            // about to set it to, it means the other caller (webhook or
            // callback) already processed this exact result — stop here
            // so we don't send the SMS / generate letters / credit the
            // target a second time.

            if ($payment_transaction->send_status === $new_status) {
                return;
            }

            $payment_transaction->send_status = $new_status;
            $payment_transaction->response = $result;
            $payment_transaction->save();

            // cancelled status {the user exit from payment page}: do nothing
            if ($is_cancelled) {
                return;
            }

            // Lock the payment row too — this is what prevents the
            // lost-update / double-increment on paid_amount.
            $payment = Payment::where('id', $payment_link->payment_id)->lockForUpdate()->firstOrFail();
            $claim = Claim::where('id', $payment->claim_id)->firstOrFail();

            // failed — whether it was previously pending or failed on
            // the first attempt, notify the user exactly once

            if ($is_failed) {
                $this->handleFailedPayment($payment_link, $payment_transaction);
                return;
            }

            if ($is_pending) {
//                if ($payment_link->type === PaymentType::FULL_PAYMENT->value) {
//                    // full payment link stays as is — only mark other links as pending
//                    $this->handleFullPaymentLinkPending($payment, $payment_link);
//                } else {
//                    $payment_link->send_status = LinkSendStatus::PENDING->value;
//                    $payment_link->save();
//                }
                return;
            }

            if ($is_successful) {
                $this->handleSuccessfulPayment($payment_link, $payment, $claim, $payment_transaction);
                $payment_transaction->paid_at = Carbon::now();
                $payment_transaction->save();
            }

            // update payment link
            if (!in_array($payment_link->send_status, [LinkSendStatus::PAID->value, LinkSendStatus::CANCELED->value])) {
                $payment_link->send_status = $is_successful ? LinkSendStatus::PAID->value : LinkSendStatus::FAILED->value;
                $payment_link->save();
            }

            if ($is_successful) {
                $collectorTargetClaimId = $claim->id;
                $collectorTargetAmount = (int) $payment_link->amount;
                $transactionForLetters = $payment_transaction;
            }
        });

        if ($collectorTargetClaimId !== null && $collectorTargetAmount !== null) {
            $claimForTarget = Claim::query()->find($collectorTargetClaimId);
            if ($claimForTarget) {
                CollectorTargetSideEffect::apply(
                    $claimForTarget,
                    $collectorTargetAmount,
                    'transaction_status_enquiry'
                );
            }
        }

        if ($transactionForLetters) {
            (new GenerateLetters($transactionForLetters))->execute();
        }
    }

    private function handleSuccessfulPayment(
        PaymentLink $payment_link,
        Payment $payment,
        Claim $claim,
        PaymentTransaction $payment_transaction
    ): void {
        $payment->paid_amount = $payment->paid_amount + $payment_transaction->amount;

        $isFullyPaid = $payment->amount <= $payment->paid_amount;

        if ($payment_link->type === PaymentType::FULL_PAYMENT->value) {
            $payment_link->amount = $payment_transaction->amount;
            $this->handleFullPaymentLink($payment, $claim, $payment_transaction);
            $payment_link->send_status = LinkSendStatus::PAID->value;
        } elseif ($isFullyPaid) {
            $payment->payment_status = PaymentStatus::PAID->value;
            $claim->status = ClaimStatus::DONE->value;
        } else {
            $claim->status = ClaimStatus::PARTIAL->value;
        }

        // update the collector_id on the payment link
        $payment_link->collector_id = $payment_transaction->collector_id;

        if ($payment_link->type === PaymentType::FULL_PAYMENT->value) {
            $payment_link->save();
        }

        $claim->save();
        $payment->save();
    }

    private function handleFullPaymentLink(Payment $payment, Claim $claim, PaymentTransaction $payment_transaction): void
    {
        $payment->links()
            ->where(function ($query) use ($payment_transaction) {
                $query->where('full_payment_transaction_ref', $payment_transaction->transaction_ref) // covers success with a prior pending status
                    ->orWhere(function ($query) {
                        $query->whereNull('full_payment_transaction_ref') // covers success without a prior pending status
                            ->where('send_status', '!=', LinkSendStatus::PENDING->value);
                    });
            })
            ->whereNotIn('send_status', [
                LinkSendStatus::CANCELED->value,
                LinkSendStatus::PAID->value,
            ])
            ->update([
                'send_status' => LinkSendStatus::CANCELED->value,
                'collector_id' => $payment_transaction->collector_id ?? null,
            ]);

        if ($payment->amount <= $payment->paid_amount) {
            $payment->payment_status = PaymentStatus::PAID->value;
            $claim->status = ClaimStatus::DONE->value;
        } else {
            $claim->status = ClaimStatus::PARTIAL->value;
        }
    }

    private function handleFullPaymentLinkPending(Payment $payment, PaymentLink $fullPaymentLink): void
    {
        // exclude the full payment link itself — it stays as is
        $payment->links()
            ->where('id', '!=', $fullPaymentLink->id)
            ->whereNotIn('send_status', [
                LinkSendStatus::CANCELED->value,
                LinkSendStatus::PAID->value,
                LinkSendStatus::PENDING->value,
            ])
            ->update([
                'send_status' => LinkSendStatus::PENDING->value,
                'full_payment_transaction_ref' => $this->chargeId, // store the full payment transaction ref
            ]);
    }

    private function handleFailedPayment(PaymentLink $payment_link, PaymentTransaction $payment_transaction): void
    {
        $payment_link->send_status = LinkSendStatus::FAILED->value;
        $payment_link->save();

        try {
            $claim = $payment_link->payment->claim;

            if (!$claim->debtor_phone) {
                return;
            }

            $message = (new MessageService())->convertTemplate(
                MessageTemplates::PAYMENT_FAILED->value,
                $payment_transaction->id
            );

            (new SmsService())->sendSms(
                $claim->debtor_phone,
                $message,
                $claim->id,
                MessageTemplates::PAYMENT_FAILED->value,
                $payment_link->id,
                template_model_id: $payment_transaction->id
            );
        } catch (Exception) {

        }
    }
}
