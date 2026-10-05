<?php

namespace App\Console\Commands;

use App\Models\Claim;
use App\Services\ReferenceGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Step 1 — Backfill Existing Claims
 * Step 2 — Validate Backfill
 *
 * Usage:
 *   php artisan rcp:backfill-claim-references             # perform backfill
 *   php artisan rcp:backfill-claim-references --validate   # validate only
 *
 * Safe to re-run: claims that already have a reference are skipped.
 *
 * Claims are processed strictly in `ORDER BY created_at, id` using manual
 * keyset (cursor) pagination on that same composite key, and one-at-a-time
 * per year lock (via ReferenceGenerationService), so sequence numbers stay
 * chronological within each year even though there is no separate
 * sequence table.
 *
 * NOTE: we deliberately do NOT use chunkById()/cursor-by-id here.
 * chunkById() paginates using `WHERE id > lastId`, which assumes id order
 * matches created_at order. For a one-shot backfill over historical data
 * (imports, restored rows, out-of-order inserts) that assumption can be
 * false, which would either skip rows or reprocess them and break the
 * chronological sequence guarantee. Keyset pagination on
 * (created_at, id) avoids that.
 */
class BackfillClaimReferences extends Command
{
    protected $signature = 'rcp:backfill-claim-references
        {--chunk=1000 : Number of claims to fetch per batch}
        {--validate : Only run the post-backfill validation checks}';

    protected $description = 'Backfill RCP claim references for existing claims and validate the result';

    public function handle(ReferenceGenerationService $referenceService): int
    {
        if ($this->option('validate')) {
            return $this->runValidation();
        }

        $this->info('Starting claim reference backfill...');

        $chunkSize = (int) $this->option('chunk');
        $processed = 0;

        $lastCreatedAt = null;
        $lastId = null;

        do {
            $query = Claim::whereNull('reference')
                ->orderBy('created_at')
                ->orderBy('id');

            // Manual keyset pagination on (created_at, id): fetch rows
            // strictly "after" the last row we processed, in
            // (created_at, id) order. This is what keeps ordering
            // correct even when primary-key order diverges from
            // created_at order.
            if ($lastCreatedAt !== null) {
                $query->where(function ($q) use ($lastCreatedAt, $lastId) {
                    $q->where('created_at', '>', $lastCreatedAt)
                        ->orWhere(function ($q2) use ($lastCreatedAt, $lastId) {
                            $q2->where('created_at', $lastCreatedAt)
                                ->where('id', '>', $lastId);
                        });
                });
            }

            $claims = $query->limit($chunkSize)->get();

            foreach ($claims as $claim) {
                // Re-check right before generating in case a
                // concurrent process (e.g. live claim creation)
                // already assigned this claim a reference.
                $fresh = Claim::whereKey($claim->id)->first();
                if ($fresh === null || $fresh->reference !== null) {
                    continue;
                }

                $referenceService->generateForBackfilledClaim(
                    $fresh->created_at,
                    function (string $reference) use ($fresh) {
                        // Direct query-builder write: bypasses the
                        // model's immutability guard on purpose,
                        // since this is the legitimate first-time
                        // assignment for a pre-existing claim.
                        DB::table('claims')
                            ->where('id', $fresh->id)
                            ->whereNull('reference')
                            ->update(['reference' => $reference]);
                    }
                );

                $processed++;
            }

            if ($claims->isNotEmpty()) {
                // Advance the cursor using the *original* claim's
                // created_at/id (not $fresh), since those are the
                // ordering keys the query above is based on and they
                // never change.
                $last = $claims->last();
                $lastCreatedAt = $last->created_at;
                $lastId = $last->id;

                $this->info("Processed {$processed} claims so far...");
            }
        } while ($claims->count() === $chunkSize);

        $this->info("Backfill complete. {$processed} claims updated.");

        return $this->runValidation();
    }

    /**
     * Step 3 — Validate Backfill.
     * Checks: no NULLs, no duplicates, correct format, and that sequence
     * numbers within each year are chronologically ordered by created_at.
     */
    private function runValidation(): int
    {
        $this->info('Validating backfill...');

        $nullCount = Claim::whereNull('reference')->count();
        if ($nullCount > 0) {
            $this->error("FAILED: {$nullCount} claim(s) still have a NULL reference.");
            return self::FAILURE;
        }

        $duplicateCount = DB::table('claims')
            ->select('reference')
            ->whereNotNull('reference')
            ->groupBy('reference')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
        if ($duplicateCount > 0) {
            $this->error("FAILED: {$duplicateCount} duplicate reference value(s) found.");
            return self::FAILURE;
        }

        $malformedCount = DB::table('claims')
            ->whereNotNull('reference')
            ->where('reference', '!~', "^RCP-[0-9]{4}-[0-9]{6}$")
            ->count();
        if ($malformedCount > 0) {
            $this->error("FAILED: {$malformedCount} reference value(s) do not match the RCP-{YEAR}-{SEQUENCE} format.");
            return self::FAILURE;
        }

        $outOfOrderCount = $this->countOutOfOrderSequences();
        if ($outOfOrderCount > 0) {
            $this->error("FAILED: {$outOfOrderCount} claim(s) have a reference sequence number that is out of chronological order (by created_at) within their year.");
            return self::FAILURE;
        }

        $this->info('Validation passed: no NULLs, no duplicates, all references correctly formatted, and sequence numbers are chronologically ordered within each year.');
        $this->info('The "reference" column is already UNIQUE at the DB level. Apply NOT NULL now if desired.');

        return self::SUCCESS;
    }

    /**
     * For each year, check that the sequence number assigned to each
     * reference increases monotonically with created_at. Uses a window
     * function (LAG) to compare each row's sequence against the
     * previous row's sequence when both are ordered by created_at.
     */
    private function countOutOfOrderSequences(): int
    {
        $rows = DB::select(<<<'SQL'
            SELECT COUNT(*) AS out_of_order_count
            FROM (
                SELECT
                    created_at,
                    CAST(SUBSTRING(reference FROM 'RCP-[0-9]{4}-([0-9]{6})') AS INTEGER) AS seq,
                    LAG(CAST(SUBSTRING(reference FROM 'RCP-[0-9]{4}-([0-9]{6})') AS INTEGER))
                        OVER (
                            PARTITION BY SUBSTRING(reference FROM 'RCP-([0-9]{4})-')
                            ORDER BY created_at, id
                        ) AS prev_seq
                FROM claims
                WHERE reference IS NOT NULL
            ) ordered
            WHERE prev_seq IS NOT NULL AND seq < prev_seq
        SQL);

        return (int) ($rows[0]->out_of_order_count ?? 0);
    }
}