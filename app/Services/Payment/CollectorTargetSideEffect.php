<?php

namespace App\Services\Payment;

use App\Models\Claim;
use App\Services\TargetService;
use Illuminate\Support\Facades\Log;

/**
 * Target updates must run outside payment/collection DB transactions.
 * A failed target write aborts PostgreSQL transactions; catching that
 * error and continuing causes SQLSTATE[25P02] on the next query.
 */
final class CollectorTargetSideEffect
{
    public static function apply(Claim $claim, int $amount, string $context): void
    {
        if (! $claim->relationLoaded('assignee')) {
            $claim->load('assignee');
        }

        if (! $claim->assignee) {
            return;
        }

        try {
            (new TargetService)->addToAchieveTarget($claim->assignee->to, $amount);
        } catch (\Throwable $exception) {
            Log::warning("{$context}: collector target was not updated.", [
                'claim_id' => $claim->id,
                'collector_id' => $claim->assignee->to,
                'amount' => $amount,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
