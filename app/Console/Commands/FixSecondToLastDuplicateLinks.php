<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixSecondToLastDuplicateLinks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment-links:fix-duplicates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Appends a space and a non-alphanumeric character to the second-to-last duplicate payment links.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Finding duplicated links...');

        // 1. Get all the links that have duplicates
        $duplicateLinks = DB::table('payment_links')
            ->select('link')
            ->groupBy('link')
            ->havingRaw('count(*) > 1')
            ->pluck('link');

        if ($duplicateLinks->isEmpty()) {
            $this->info('No duplicates found. You are good to go!');
            return Command::SUCCESS;
        }

        $updatedCount = 0;

        // 2. Process each duplicated link
        foreach ($duplicateLinks as $linkString) {
            // 1. Get all records for this link, ordered by ID
            $records = DB::table('payment_links')
                ->where('link', $linkString)
                ->orderBy('id', 'asc')
                ->get();

            // 2. Identify the last record (the one we leave alone)
            $lastRecordId = $records->last()->id;

            // 3. Update every record that is NOT the last one
            foreach ($records as $record) {
                if ($record->id !== $lastRecordId) {
                    $newLink = $record->link . '@' . $record->id;

                    DB::table('payment_links')
                        ->where('id', $record->id)
                        ->update(['link' => $newLink]);

                    $this->line("Fixed ID {$record->id}: '{$linkString}' -> '{$newLink}'");
                    $updatedCount++;
                }
            }
        }

        $this->info("Done! Successfully modified {$updatedCount} second-to-last duplicate records.");
        return Command::SUCCESS;
    }
}
