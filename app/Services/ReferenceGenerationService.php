<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Central, concurrency-safe RCP Claim Reference generator.
 *
 * Format:  RCP-{YEAR}-{SEQUENCE}   e.g. RCP-2026-000123
 *
 * There is NO separate sequence table. The next number is derived
 * directly from the `reference` column on `claims` itself
 * (MAX per year), but the read + write is serialized through an
 * application-level atomic lock (Cache::lock) keyed per year, so it is
 * NOT a naive/unsafe "MAX(reference) + 1" — concurrent callers cannot
 * read the same MAX and collide, because only one holder of the lock
 * for a given year can compute + persist a reference at a time.
 *
 * Requires a cache driver that supports atomic locks (redis, database,
 * memcached, dynamodb) — this is configured via config/cache.php.
 *
 * IMPORTANT:
 * - This is the ONLY place allowed to mint a reference. Both the
 *   historical backfill command and live claim creation (UI / Partner
 *   API / Imports) MUST call this service.
 * - The `$persist` callback is executed WHILE the lock is held, so the
 *   generated reference must be written to the claims row before the
 *   lock is released (guaranteeing no two callers ever persist the
 *   same reference).
 */
class ReferenceGenerationService
{
    private const PREFIX = 'RCP';
    private const SEQUENCE_PAD_LENGTH = 6;
    private const LOCK_TTL_SECONDS = 10;
    private const LOCK_WAIT_SECONDS = 5;

    /**
     * Generate the next reference for the given year and persist it via
     * the supplied callback, all while holding an exclusive per-year lock.
     *
     * @param  callable(string $reference): void  $persist  Called with the
     *         generated reference; must write it to the claim row
     *         (e.g. DB::table('claims')->where('id', $id)->update([...])).
     */
    public function generateForYear(int $year, callable $persist): string
    {
        $lock = Cache::lock($this->lockKey($year), self::LOCK_TTL_SECONDS);

        return $lock->block(self::LOCK_WAIT_SECONDS, function () use ($year, $persist) {
            $reference = $this->format($year, $this->nextSequenceNumber($year));

            $persist($reference);

            return $reference;
        });
    }

    /**
     * Generate a reference for a claim being created "now" (UI / Partner
     * API / Imports). Derives the year from the current date.
     */
    public function generateForNewClaim(callable $persist): string
    {
        return $this->generateForYear((int) now()->format('Y'), $persist);
    }

    /**
     * Used by the backfill command, which must derive the year from
     * claims.created_at rather than "now".
     */
    public function generateForBackfilledClaim(\DateTimeInterface $createdAt, callable $persist): string
    {
        return $this->generateForYear((int) $createdAt->format('Y'), $persist);
    }

    /**
     * Reads the highest existing sequence number for the given year
     * directly from claims.reference. Only ever called while the
     * per-year lock is held, so this is safe despite not being a
     * row-level DB lock.
     */
    private function nextSequenceNumber(int $year): int
    {
        $prefix = self::PREFIX . '-' . $year . '-';

        $last = DB::table('claims')
            ->where('reference', 'like', $prefix . '%')
            ->orderByDesc('reference')
            ->value('reference');

        if ($last === null) {
            return 1;
        }

        $lastNumber = (int) substr($last, strlen($prefix));

        return $lastNumber + 1;
    }

    private function format(int $year, int $sequenceNumber): string
    {
        $padded = str_pad((string) $sequenceNumber, self::SEQUENCE_PAD_LENGTH, '0', STR_PAD_LEFT);

        return self::PREFIX . '-' . $year . '-' . $padded;
    }

    private function lockKey(int $year): string
    {
        return "rcp:claim-reference-generation:{$year}";
    }
}