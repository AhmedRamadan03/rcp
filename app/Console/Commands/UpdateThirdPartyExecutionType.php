<?php

namespace App\Console\Commands;


use App\Models\ThirdParty;
use Illuminate\Console\Command;

class UpdateThirdPartyExecutionType extends Command
{
    protected $signature = 'third-parties:update-execution-type {--dry-run}';

    protected $description = 'Backfill the config.execution_type column on existing third_parties rows';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $updated = 0;
        $unchanged = 0;
        $skipped = 0;

        ThirdParty::withTrashed()
            ->chunkById(100, function ($thirdParties) use (&$updated, &$unchanged, &$skipped, $dryRun) {
                foreach ($thirdParties as $thirdParty) {
                    $configuredExecutionType = config(
                        "third-party.{$thirdParty->type}.{$thirdParty->vendor_label}.config." . ThirdParty::CONFIG_EXECUTION_TYPE
                    );

                    if (!$configuredExecutionType) {
                        $this->warn("No execution_type configured for id={$thirdParty->id} type={$thirdParty->type} vendor_label={$thirdParty->vendor_label}, skipping.");
                        $skipped++;
                        continue;
                    }

                    $currentExecutionType = $thirdParty->config['execution_type'] ?? null;

                    if ($currentExecutionType === $configuredExecutionType) {
                        $unchanged++;
                        continue;
                    }

                    $this->line(
                        "id={$thirdParty->id} type={$thirdParty->type} vendor_label={$thirdParty->vendor_label}: "
                        . ($currentExecutionType ?? 'null') . " -> {$configuredExecutionType}"
                    );

                    if (!$dryRun) {
                        $thirdParty->update([
                            'config' => array_merge($thirdParty->config ?? [], [
                                'execution_type' => $configuredExecutionType,
                            ]),
                        ]);
                    }

                    $updated++;
                }
            });

        $this->info(
            ($dryRun ? '[DRY RUN] ' : '')
            . "Updated: {$updated}, Unchanged: {$unchanged}, Skipped: {$skipped}"
        );

        return self::SUCCESS;
    }
}