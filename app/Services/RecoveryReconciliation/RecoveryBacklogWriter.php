<?php

namespace App\Services\RecoveryReconciliation;

use App\Enums\ClaimStatus;
use App\Enums\LinkSendStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PaymentType;
use App\Enums\TransactionType;
use App\Helpers\PaymentLinkHelper;
use App\Models\Claim;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\PaymentTransaction;
use App\Services\ClaimService;
use Illuminate\Support\Facades\DB;

/**
 * Writes one claim's backlog transactions.
 *
 * Deliberately calls nothing that notifies: no PaymentReminder, no
 * NotificationService, no TargetService, no SmsService/IvrService/MessageService,
 * no events, no discharge generation. There are no model observers and no
 * model-event listeners in this application, so not calling them is sufficient.
 */
class RecoveryBacklogWriter
{
    /** Marks every row this feature creates, forever. */
    public const REF_PREFIX = 'RECOVERY-BACKLOG:';

    public function __construct(private ClaimService $claims) {}

    /**
     * Apply one claim inside its own transaction.
     *
     * Returns either ['status' => 'applied', ...] or ['status' => 'skipped', 'reason' => ...].
     * Scoping the transaction per claim keeps a late failure from rolling back
     * hours of work and avoids holding row locks across thousands of claims.
     *
     * @param  list<array<string, mixed>>  $rows  one claim's rows from a scenario file
     * @return array<string, mixed>
     */
    public function apply(int $claimId, array $rows): array
    {
        return DB::transaction(function () use ($claimId, $rows) {
            $claim = Claim::whereKey($claimId)->lockForUpdate()->first();

            if (! $claim) {
                return $this->skip('claim_missing');
            }

            // Lock the exact payment the file names, and confirm it still owns it.
            $payment = Payment::whereKey((int) $rows[0]['payment_id'])
                ->where('claim_id', $claim->id)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                return $this->skip('payment_missing_or_changed');
            }

            if (Payment::where('claim_id', $claim->id)->count() > 1) {
                return $this->skip('multiple_payments');
            }

            if ($abort = $this->abortReason($claim, $payment)) {
                return $this->skip($abort);
            }

            return $this->write($claim, $payment, $rows);
        });
    }

    /**
     * The safety interlock: has anything changed since the split that would
     * make writing this claim unsafe? Never a reinterpretation — only an abort.
     */
    private function abortReason(Claim $claim, Payment $payment): ?string
    {
        if ($this->halalas($payment->paid_amount) > 0) {
            return 'already_has_paid_amount';
        }

        $transactions = PaymentTransaction::where('payment_id', $payment->id)->get(['send_status', 'transaction_ref']);

        if ($transactions->contains(fn ($t) => PaymentTransactionStatus::PAID->value === $t->send_status)) {
            return 'already_has_paid_transaction';
        }

        if ($transactions->contains(fn ($t) => str_starts_with((string) $t->transaction_ref, self::REF_PREFIX))) {
            return 'already_applied';
        }

        if ($payment->discounts()->where('is_active', 1)->exists()) {
            return 'has_active_discount';
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function write(Claim $claim, Payment $payment, array $rows): array
    {
        usort($rows, fn ($a, $b) => $a['order'] <=> $b['order']);

        // Mirrors manualCollection(): no stale link stays payable once closed.
        PaymentLink::where('payment_id', $payment->id)
            ->where('send_status', '<>', LinkSendStatus::PAID->value)
            ->update(['send_status' => LinkSendStatus::CANCELED->value]);

        $previousStatus = $claim->status;
        $collectorId = $rows[0]['collector_id'];
        $type = count($rows) === 1 ? PaymentType::FULL_PAYMENT->value : PaymentType::INSTALLMENT_PAYMENT->value;

        $refs = [];
        $total = 0;

        foreach ($rows as $row) {
            $date = $row['date'];
            $amount = $this->sar($row['amount']);

            $link = PaymentLinkHelper::createUnique(
                paymentId: $payment->id,
                amount: $amount,
                dueDate: $date->toDateString(),
                type: $type,
                sendStatus: LinkSendStatus::PAID->value,
                lastAccess: $date->toDateTimeString(),
                order: $row['order'],
                collectorId: $collectorId,
            );

            // Backdate so collections-by-date reporting stays truthful; updated_at
            // keeps the run time, so what this command touched is still visible.
            $link->created_at = $date;
            $link->save();

            $ref = self::REF_PREFIX.$row['source_row'];

            PaymentTransaction::create([
                'payment_id' => $payment->id,
                'link_id' => $link->id,
                'send_status' => PaymentTransactionStatus::PAID->value,
                'amount' => $amount,
                'payment_type' => TransactionType::MANUAL->value,
                'transaction_ref' => $ref,
                'collector_id' => $collectorId,
                'response' => json_encode([]),
                'created_at' => $date,
            ]);

            $refs[] = $ref;
            $total += $row['amount'];
        }

        $payment->paid_amount = $this->sar($total);

        if ($total >= $this->halalas($payment->amount)) {
            $payment->payment_status = PaymentStatus::PAID->value;
        }

        $payment->save();

        $claim->update([
            'status' => ClaimStatus::COLLECTED_OUT_OF_SYSTEM->value,
            'is_close' => 1,
        ]);

        $this->claims->writeToClaimStatusHistory(
            $claim->id,
            $previousStatus,
            ClaimStatus::COLLECTED_OUT_OF_SYSTEM->value,
            $collectorId,
            ['note' => 'Recovery backlog import'],
        );

        return [
            'status' => 'applied',
            'claim_id' => $claim->id,
            'previous_status' => $previousStatus,
            'transactions' => count($rows),
            'halalas' => $total,
            'refs' => $refs,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function skip(string $reason): array
    {
        return ['status' => 'skipped', 'reason' => $reason];
    }

    private function halalas(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function sar(int $halalas): float
    {
        return round($halalas / 100, 2);
    }
}
