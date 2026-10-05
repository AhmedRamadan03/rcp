<?php

namespace App\Services\Claim;

use App\Actions\GenerateLetters;
use App\BusinessLogic\PaymentReminder\PaymentReminder;
use App\Enums\ClaimStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\LinkSendStatus;
use App\Enums\RequestStatus;
use App\Enums\TransactionType;
use App\Helpers\PaymentLinkHelper;
use App\Models\AdditionalFile;
use App\Models\Claim;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Setting;
use App\Models\StatusChangeRequest;
use App\Services\ClaimService;
use App\Services\Payment\CollectorTargetSideEffect;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shared "manual collection" logic — used by BOTH
 * ClaimController::claimBankTransfer() (website) and
 * ManualCollectedStatusAction (Partner API).
 *
 * IMPORTANT: like reject/cancel, the original ClaimService::manualCollection()
 * has NO approval gate (its createRequestStatus() call is commented
 * out in production) — it always executes immediately today. $options
 * is accepted for interface consistency but currently has no effect.
 *
 * Two latent bugs fixed from the original (both were "undefined
 * variable" risks, not "wrong value" risks):
 *   1. $transaction / $payment_link were only ever assigned INSIDE the
 *      `if ($payment->amount != $payment->paid_amount)` branch, but
 *      referenced again AFTER it unconditionally. If a claim's payment
 *      was already fully paid, both would be undefined variables at
 *      that point (PHP 8 warning, and a fatal error trying to read
 *      ->amount off a non-existent $payment_link). Fixed by
 *      initializing both to null up front and guarding every later use.
 *   2. Everything is now wrapped in a single DB transaction, since this
 *      touches Payment, PaymentTransaction, PaymentLink, Claim, and
 *      StatusChangeRequest together — partial application on failure
 *      would leave inconsistent payment state.
 */
class ManualCollectionService
{
    public function __construct(
        private readonly ClaimService $claimService,
    ) {
    }

    public function execute(
        Claim $claim,
        array $data,
        ?AdditionalFile $additionalFile,
        int $actingUserId,
        ?ClaimActionOptions $options = null
    ): void {
        $options ??= ClaimActionOptions::systemApi();

        $transactionForLetters = null;
        $collectorTargetAmount = null;

        DB::transaction(function () use ($claim, $data, $actingUserId, &$transactionForLetters, &$collectorTargetAmount) {
            $previousStatus = $claim->status;

            $payment = Payment::where('claim_id', $claim->id)->first();

            $payment->links()
                ->where('send_status', '<>', LinkSendStatus::PAID->value)
                ->update(['send_status' => LinkSendStatus::CANCELED->value]);

            $paymentLink = null;
            $transaction = null;

            if ($payment->amount != $payment->paid_amount) {
                $paymentLink = PaymentLinkHelper::createUnique(
                    paymentId: $payment->id,
                    amount: $payment->amount - $payment->paid_amount,
                    dueDate: Carbon::today()->format('Y-m-d')
                );

                $transaction = PaymentTransaction::create([
                    'payment_id' => $paymentLink->payment_id,
                    'link_id' => $paymentLink->id,
                    'send_status' => PaymentTransactionStatus::PAID->value,
                    'amount' => $payment->amount - $payment->paid_amount,
                    'payment_type' => TransactionType::MANUAL->value,
                    'collector_id' => $claim->assignee?->to ?? null,
                ]);

                $paymentLink->send_status = LinkSendStatus::PAID->value;
                $paymentLink->views_count = $paymentLink->views_count + 1;
                $paymentLink->last_access = Carbon::today()->format('Y-m-d');
                $paymentLink->collector_id = $claim->assignee?->to ?? null;
                $paymentLink->save();
            }

            $payment->paid_amount = $payment->amount;
            $payment->payment_status = PaymentStatus::PAID->value;
            $payment->save();

            $claim->update(['status' => ClaimStatus::MANUAL_COLLECTED->value]);

            \App\BusinessLogic\PaymentReminder\PaymentReminder::handel(
                Setting::paymentReminderStatus(ClaimStatus::MANUAL_COLLECTED->value),
                $claim->id
            );

            StatusChangeRequest::query()
                ->where('claim_id', $claim->id)
                ->update(['request_status' => RequestStatus::REJECTED->value]);

            $this->claimService->writeToClaimStatusHistory(
                $claim->id,
                $previousStatus,
                ClaimStatus::MANUAL_COLLECTED->value,
                $actingUserId,
                $data
            );

            if ($paymentLink) {
                $collectorTargetAmount = (int) $paymentLink->amount;
            }

            $transactionForLetters = $transaction;
        });

        if ($collectorTargetAmount !== null) {
            CollectorTargetSideEffect::apply(
                $claim->fresh(),
                $collectorTargetAmount,
                'manual_collection'
            );
        }

        if ($transactionForLetters) {
            (new GenerateLetters($transactionForLetters))->execute();
        }

        PaymentReminder::handel(Setting::paymentReminderStatus(ClaimStatus::MANUAL_COLLECTED->value), $claim->id);

    }
}