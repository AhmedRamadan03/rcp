<?php

namespace App\Console\Commands;

use App\Enums\PaymentType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdatePayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-payments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill missing link/verification_code on payments from their full payment link.';

    /**
     * Number of payment rows to process per chunk. Keep this modest to
     * bound memory usage regardless of table size.
     */
    private const CHUNK_SIZE = 500;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $updated = 0;
        $skipped = 0;
        $failed  = 0;

        // chunkById iterates in batches ordered by primary key, re-querying
        // each batch fresh from the DB. This keeps memory flat no matter how
        // many rows match, and — unlike chunk() — stays correct even though
        // we are updating the very rows we're filtering on (chunk() with an
        // offset-based cursor can skip or repeat rows once matching rows are
        // updated out from under the WHERE clause; chunkById avoids that by
        // paging on id, not on the count of remaining matches).
        DB::table('payments')
            ->whereNull('link')
            ->whereNull('verification_code')
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function ($paymentsChunk) use (&$updated, &$skipped, &$failed) {

                $paymentIds = $paymentsChunk->pluck('id');

                // One query per chunk instead of one query per row.
                $fullPaymentLinks = DB::table('payment_links')
                    ->whereIn('payment_id', $paymentIds)
                    ->where('type', PaymentType::FULL_PAYMENT->value)
                    ->get()
                    ->keyBy('payment_id');

                foreach ($paymentsChunk as $payment) {
                    $fullPaymentLink = $fullPaymentLinks->get($payment->id);

                    if (! $fullPaymentLink) {
                        $skipped++;
                        continue;
                    }

                    try {
                        DB::table('payments')
                            ->where('id', $payment->id)
                            ->update([
                                'link'              => $fullPaymentLink->link,
                                'verification_code' => $fullPaymentLink->verification_code,
                            ]);

                        $updated++;
                    } catch (\Throwable $th) {
                        $failed++;

                        Log::error('UpdatePayments: failed to backfill payment', [
                            'payment_id' => $payment->id,
                            'error'      => $th->getMessage(),
                        ]);
                    }
                }
            }, 'id');

        $this->info("Done. Updated: {$updated}, Skipped (no full payment link): {$skipped}, Failed: {$failed}");
    }
}