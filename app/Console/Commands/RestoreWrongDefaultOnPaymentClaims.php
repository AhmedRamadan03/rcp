<?php

namespace App\Console\Commands;

use App\Enums\ClaimStatus;
use App\Models\Claim;
use App\Services\ClaimService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RestoreWrongDefaultOnPaymentClaims extends Command
{
    protected $signature = 'app:restore-wrong-default-on-payment-claims
        {--execute : Apply changes. Without this flag, dry-run only.}';

    protected $description = 'Restore claims wrongly converted to default_in_payment_by_system back to their previous finished status';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $rows = WrongDefaultOnPaymentClaims::rows();

        if ($execute) {
            $this->warn('Running in EXECUTE mode. Claim statuses will be updated.');
        } else {
            $this->info('Running in DRY-RUN mode. No changes will be made. Pass --execute to apply.');
        }

        $wouldRestore = 0;
        $restored = 0;
        $skipped = 0;
        $notFound = 0;
        $claimService = new ClaimService;

        foreach ($rows as $row) {
            $claim = Claim::find($row->claim_id);

            if (! $claim) {
                $notFound++;
                continue;
            }

            if ($claim->status !== ClaimStatus::DEFAULT_IN_PAYMENT_BY_SYSTEM->value) {
                $skipped++;
                continue;
            }

            if (! $execute) {
                $wouldRestore++;
                continue;
            }

            DB::transaction(function () use ($claim, $row, $claimService) {
                $claim->status = $row->previous_status;

                if (in_array($row->previous_status, WrongDefaultOnPaymentClaims::closedStatuses(), true)) {
                    $claim->is_close = 1;
                }

                $claim->save();

                $claimService->writeToClaimStatusHistory(
                    $claim->id,
                    ClaimStatus::DEFAULT_IN_PAYMENT_BY_SYSTEM->value,
                    $row->previous_status,
                    $claim->user_id
                );
            });

            $restored++;
        }

        $this->newLine();
        $this->line("Affected claims: {$rows->count()}");
        $this->line("Skipped (already not default_in_payment_by_system): {$skipped}");
        $this->line("Not found: {$notFound}");

        if ($execute) {
            $this->info("Restored: {$restored}");
        } else {
            $this->info("Would restore: {$wouldRestore}");
        }

        return self::SUCCESS;
    }
}
