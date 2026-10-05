<?php

namespace App\Services\RecoveryReconciliation;

use App\Enums\PaymentTransactionStatus;
use Illuminate\Support\Facades\DB;

/**
 * Sorts backlog claims into exactly one scenario each, against live data.
 *
 * Every classification decision lives here and happens once. recovery:apply
 * consumes the resulting files and infers nothing.
 */
class RecoveryScenarioClassifier
{
    public const NOT_IN_RCP = 'not-in-rcp';

    public const AMBIGUOUS_CLAIM = 'ambiguous-claim';

    public const NO_PAYMENT_ROW = 'no-payment-row';

    public const MULTIPLE_PAYMENTS = 'multiple-payments';

    public const HAS_ACTIVE_DISCOUNT = 'has-active-discount';

    public const ALREADY_RECORDED = 'already-recorded';

    public const OVERLAP_UNCLEAR = 'overlap-unclear';

    public const MULTIPLE_COMPLETE = 'multiple-complete';

    public const STILL_COLLECTING = 'still-collecting';

    public const OVER_COLLECTED = 'over-collected';

    public const FULL = 'full';

    public const DISCOUNT = 'discount';

    /** Scenarios recovery:apply will act on. */
    public const APPLICABLE = [self::FULL, self::DISCOUNT];

    private const LOOKUP_CHUNK = 5000;

    /**
     * Classify every claim, handing each result to $onClaim as it is produced.
     *
     * Results are streamed rather than returned, and each group is released once
     * classified, so peak memory does not grow with the size of the backlog.
     *
     * @param  array<string, list<array<string, mixed>>>  $claimGroups  keyed by claim number, from RecoveryWorkbookReader
     * @param  callable(array<string, mixed>): void  $onClaim
     */
    public function classify(array &$claimGroups, int $fallbackUserId, callable $onClaim, ?callable $onProgress = null): void
    {
        foreach (array_chunk(array_keys($claimGroups), self::LOOKUP_CHUNK) as $chunk) {
            $lookup = $this->lookup($chunk);

            foreach ($chunk as $claimNumber) {
                $onClaim($this->classifyClaim($claimNumber, $claimGroups[$claimNumber], $lookup, $fallbackUserId));

                unset($claimGroups[$claimNumber]);
            }

            unset($lookup);

            if ($onProgress) {
                $onProgress(count($chunk));
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $lookup
     * @return array<string, mixed>
     */
    private function classifyClaim(string $claimNumber, array $rows, array $lookup, int $fallbackUserId): array
    {
        usort($rows, fn ($a, $b) => [$a['date'], $a['source_row']] <=> [$b['date'], $b['source_row']]);

        $excelTotal = array_sum(array_column($rows, 'amount'));
        $completeCount = count(array_filter($rows, fn ($r) => $r['settlement'] === 'complete'));

        $base = [
            'claim_number' => $claimNumber,
            'excel_total' => $excelTotal,
            'complete_count' => $completeCount,
            'rows' => $rows,
            'claim_id' => null,
            'payment_id' => null,
            'matched_by' => null,
            'claim_amount' => null,
            'payment_amount' => null,
            'payment_paid_amount' => null,
            'claim_status' => null,
            'system_paid_total' => null,
            'collector_id' => $fallbackUserId,
            'collector_name' => null,
        ];

        [$claim, $matchedBy] = $this->resolveClaim($claimNumber, $rows, $lookup);

        if ($claim === null) {
            return $base + ['scenario' => $matchedBy];
        }

        $collector = $lookup['assignees'][$claim->id] ?? null;

        $base = array_merge($base, [
            'claim_id' => $claim->id,
            'matched_by' => $matchedBy,
            'claim_amount' => $this->halalas($claim->amount),
            'claim_status' => $claim->status,
            'collector_id' => $collector->to ?? $fallbackUserId,
            'collector_name' => $collector->name ?? null,
        ]);

        $payments = $lookup['payments'][$claim->id] ?? [];

        if ($payments === []) {
            return $base + ['scenario' => self::NO_PAYMENT_ROW];
        }

        if (count($payments) > 1) {
            return $base + ['scenario' => self::MULTIPLE_PAYMENTS];
        }

        $payment = $payments[0];

        $base = array_merge($base, [
            'payment_id' => $payment->id,
            'payment_amount' => $this->halalas($payment->amount),
            'payment_paid_amount' => $this->halalas($payment->paid_amount),
        ]);

        if (isset($lookup['discounts'][$claim->id])) {
            return $base + ['scenario' => self::HAS_ACTIVE_DISCOUNT];
        }

        // payments.paid_amount and the paid-transaction sum are maintained by
        // different code paths and can disagree, so treat either as evidence.
        $paidAmount = $this->halalas($payment->paid_amount);
        $transactionSum = $this->halalas($lookup['transactions'][$payment->id] ?? 0);
        $systemTotal = max($paidAmount, $transactionSum);

        $base['system_paid_total'] = $systemTotal;

        if ($systemTotal > 0) {
            return $base + [
                'scenario' => $systemTotal === $excelTotal ? self::ALREADY_RECORDED : self::OVERLAP_UNCLEAR,
            ];
        }

        if ($completeCount > 1) {
            return $base + ['scenario' => self::MULTIPLE_COMPLETE];
        }

        if ($completeCount === 0) {
            return $base + ['scenario' => self::STILL_COLLECTING];
        }

        return $base + ['scenario' => match (true) {
            $excelTotal > $base['claim_amount'] => self::OVER_COLLECTED,
            $excelTotal === $base['claim_amount'] => self::FULL,
            default => self::DISCOUNT,
        }];
    }

    /**
     * Resolve the workbook claim number to a single RCP claim.
     *
     * When several claims share the number, the workbook policy number breaks
     * the tie — but only then. Policy is never an extra filter on a claim
     * number that already matches uniquely, because RCP stores placeholders
     * ("UNKNOWN", "0000000000", blank) often enough that requiring agreement
     * would discard clean claims.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $lookup
     * @return array{0: ?object, 1: string}
     */
    private function resolveClaim(string $claimNumber, array $rows, array $lookup): array
    {
        $candidates = $lookup['claims'][$claimNumber] ?? [];

        if ($candidates === []) {
            return [null, self::NOT_IN_RCP];
        }

        if (count($candidates) === 1) {
            return [$candidates[0], 'claim_number'];
        }

        $policies = array_filter(array_map(
            fn ($row) => $this->normalise($row['policy_number']),
            $rows
        ));

        $matches = array_values(array_filter(
            $candidates,
            fn ($claim) => in_array($this->normalise($claim->policy_number), $policies, true)
        ));

        return count($matches) === 1
            ? [$matches[0], 'claim_number + policy']
            : [null, self::AMBIGUOUS_CLAIM];
    }

    /**
     * Fetch everything the rules need for one batch of claim numbers.
     *
     * @param  list<string>  $claimNumbers
     * @return array<string, mixed>
     */
    private function lookup(array $claimNumbers): array
    {
        $claims = DB::table('claims')
            ->whereIn('claim_number', $claimNumbers)
            ->whereNull('deleted_at')
            ->get(['id', 'claim_number', 'amount', 'status', 'policy_number']);

        $claimIds = $claims->pluck('id')->all();

        $payments = DB::table('payments')
            ->whereIn('claim_id', $claimIds)
            ->whereNull('deleted_at')
            ->get(['id', 'claim_id', 'amount', 'paid_amount', 'payment_status']);

        $transactions = DB::table('payment_transactions')
            ->whereIn('payment_id', $payments->pluck('id')->all())
            ->where('send_status', PaymentTransactionStatus::PAID->value)
            ->groupBy('payment_id')
            ->selectRaw('payment_id, sum(amount) as total')
            ->pluck('total', 'payment_id');

        $discounts = DB::table('discounts')
            ->whereIn('claim_id', $claimIds)
            ->where('is_active', 1)
            ->pluck('claim_id', 'claim_id');

        $assignees = DB::table('assignings')
            ->join('users', 'users.id', '=', 'assignings.to')
            ->whereIn('assignings.claim_id', $claimIds)
            ->where('assignings.is_active', 1)
            ->whereNull('assignings.deleted_at')
            ->orderBy('assignings.id')
            ->get(['assignings.claim_id', 'assignings.to', 'users.name'])
            ->keyBy('claim_id'); // later rows win, matching Claim::assignee()'s latest()

        return [
            'claims' => $claims->groupBy('claim_number')->map(fn ($group) => $group->values()->all())->all(),
            'payments' => $payments->groupBy('claim_id')->map(fn ($group) => $group->values()->all())->all(),
            'transactions' => $transactions->all(),
            'discounts' => $discounts->all(),
            'assignees' => $assignees->all(),
        ];
    }

    private function halalas(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function normalise(?string $value): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $value));
    }
}
