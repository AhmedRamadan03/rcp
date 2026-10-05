<?php

namespace App\Console\Commands;

use App\Models\ThirdParty;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixSmsGigVendorName extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'thirdparty:fix-gig-vendor-name
                            {--dry-run : Show affected rows without updating them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update ThirdParty records with type=sms and vendor_name=GIG to Gig';

    public function handle(): int
    {
        $query = ThirdParty::query()
            ->where('type', 'sms')
            ->where('vendor_name', 'GIG');

        $count = $query->count();

        if ($count === 0) {
            $this->info('No matching records found. Nothing to update.');
            return self::SUCCESS;
        }

        $this->info("Found {$count} record(s) with type=sms and vendor_name=GIG.");

        if ($this->option('dry-run')) {
            $query->get(['id', 'client_id', 'type', 'vendor_name', 'vendor_label'])
                ->each(fn ($tp) => $this->line("  - ID {$tp->id} (client_id: {$tp->client_id})"));
            $this->comment('Dry run only — no changes made.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($query) {
            $query->update(['vendor_name' => 'Gig']);
        });

        $this->info("Successfully updated {$count} record(s): vendor_name 'GIG' -> 'Gig'.");

        return self::SUCCESS;
    }
}