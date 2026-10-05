<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\RecoveryReconciliation\RecoveryScenarioClassifier;
use App\Services\RecoveryReconciliation\RecoveryWorkbookReader;
use Illuminate\Console\Command;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\Console\Helper\TableSeparator;

/**
 * Splits the recovery backlog into one Excel file per scenario.
 *
 * Read-only: SELECTs plus file writes. Every classification decision happens
 * here so recovery:apply can consume a reviewed file and infer nothing.
 */
class SplitRecoveryBacklog extends Command
{
    protected $signature = 'recovery:split
        {file : Path to the recovery workbook}
        {--user= : Fallback user id for claims with no active assignee}
        {--output= : Output directory, defaults to storage/app/recovery-scenarios/<timestamp>}';

    protected $description = 'Classify the recovery backlog and write one Excel file per scenario';

    /** Scenario file columns — the contract with recovery:apply. */
    private const HEADERS = [
        'Scenario', 'Source Row', 'Claim NO.', 'Claim ID', 'Payment ID', 'Policy No.', 'Matched By',
        'Recovery Collected', 'Recovery Date', 'Settlment Type ', 'Order',
        'Claim Amount', 'Payment Amount', 'Payment Paid Amount', 'Claim Status',
        'Claim Total Collected', 'Collector ID', 'Collector Name',
    ];

    public function handle(RecoveryWorkbookReader $reader, RecoveryScenarioClassifier $classifier): int
    {
        $file = $this->argument('file');

        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $fallbackUserId = (int) $this->option('user');

        if (! $fallbackUserId || ! User::whereKey($fallbackUserId)->exists()) {
            $this->error('--user must be an existing user id (the fallback actor for unassigned claims).');

            return self::FAILURE;
        }

        $directory = $this->option('output')
            ?: storage_path('app/recovery-scenarios/'.now()->format('Y-m-d_His'));

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->error("Could not create output directory: {$directory}");

            return self::FAILURE;
        }

        $startedAt = microtime(true);

        $this->info('Reading workbook...');
        $source = $reader->readSource($file);

        $claimCount = count($source['claims']);

        $this->info('Classifying '.number_format($claimCount).' claims...');
        $bar = $this->output->createProgressBar($claimCount);

        // Transaction rows are streamed straight into per-scenario writers as
        // they are classified. Only the small per-claim summary lines are held,
        // so memory stays flat regardless of how large the backlog is.
        $writers = [];
        $summary = [];

        $classifier->classify(
            $source['claims'],
            $fallbackUserId,
            function (array $claim) use (&$writers, &$summary, $directory) {
                $this->writeClaim($writers, $summary, $directory, $claim);
            },
            fn ($done) => $bar->advance($done),
        );

        $bar->finish();
        $this->newLine(2);

        foreach ($writers as $scenario => $writer) {
            $this->finishFile($writer, $scenario, $summary[$scenario]);
        }

        $this->writeManifest($directory, $file, $source, $claimCount, $summary, $fallbackUserId, $startedAt);
        $this->report($source, $summary, $directory);

        return self::SUCCESS;
    }

    /**
     * Append one classified claim to its scenario file, opening it on first use.
     *
     * @param  array<string, Writer>  $writers
     * @param  array<string, array<string, mixed>>  $summary
     * @param  array<string, mixed>  $claim
     */
    private function writeClaim(array &$writers, array &$summary, string $directory, array $claim): void
    {
        $scenario = $claim['scenario'];

        if (! isset($writers[$scenario])) {
            $writer = new Writer;
            $writer->openToFile($directory.'/scenario-'.$scenario.'.xlsx');
            $writer->getCurrentSheet()->setName('Transactions');
            $writer->addRow(Row::fromValues(self::HEADERS));

            $writers[$scenario] = $writer;
            $summary[$scenario] = [
                'claims' => 0,
                'rows' => 0,
                'halalas' => 0,
                'file' => 'scenario-'.$scenario.'.xlsx',
                'lines' => [],
            ];
        }

        $writer = $writers[$scenario];

        foreach (array_values($claim['rows']) as $index => $row) {
            $writer->addRow(Row::fromValues([
                $scenario,
                $row['source_row'],
                $claim['claim_number'],
                $claim['claim_id'],
                $claim['payment_id'],
                $row['policy_number'],
                $claim['matched_by'],
                $this->sar($row['amount']),
                $row['date'],
                $row['settlement'],
                $index + 1,
                $this->sar($claim['claim_amount']),
                $this->sar($claim['payment_amount']),
                $this->sar($claim['payment_paid_amount']),
                $claim['claim_status'],
                $this->sar($claim['excel_total']),
                $claim['collector_id'],
                $claim['collector_name'],
            ]));
        }

        $summary[$scenario]['claims']++;
        $summary[$scenario]['rows'] += count($claim['rows']);
        $summary[$scenario]['halalas'] += $claim['excel_total'];
        $summary[$scenario]['lines'][] = [
            $claim['claim_number'],
            $claim['claim_id'],
            count($claim['rows']),
            $this->sar($claim['excel_total']),
            $this->sar($claim['claim_amount']),
            $claim['claim_status'],
        ];
    }

    /**
     * Add the Summary sheet and close the file.
     *
     * @param  array<string, mixed>  $entry
     */
    private function finishFile(Writer $writer, string $scenario, array $entry): void
    {
        $writer->addNewSheetAndMakeItCurrent()->setName('Summary');

        $writer->addRow(Row::fromValues(['Scenario', $scenario]));
        $writer->addRow(Row::fromValues(['Rule', $this->rule($scenario)]));
        $writer->addRow(Row::fromValues(['Claims', $entry['claims']]));
        $writer->addRow(Row::fromValues(['Transactions', $entry['rows']]));
        $writer->addRow(Row::fromValues(['Total Collected (SAR)', $this->sar($entry['halalas'])]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Claim NO.', 'Claim ID', 'Transactions', 'Total Collected', 'Claim Amount', 'Claim Status']));

        foreach ($entry['lines'] as $line) {
            $writer->addRow(Row::fromValues($line));
        }

        $writer->close();
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, array<string, mixed>>  $summary
     */
    private function writeManifest(string $directory, string $file, array $source, int $claimCount, array $summary, int $fallbackUserId, float $startedAt): void
    {
        $manifest = [
            'schema_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'source_file' => realpath($file),
            'source_sha256' => hash_file('sha256', $file),
            'source_rows' => $source['rows'],
            'source_claims' => $claimCount,
            'unparsable_rows' => count($source['unparsable']),
            'fallback_user_id' => $fallbackUserId,
            'applicable_scenarios' => RecoveryScenarioClassifier::APPLICABLE,
            'scenarios' => array_map(fn ($entry) => [
                'claims' => $entry['claims'],
                'transactions' => $entry['rows'],
                'total_sar' => $this->sar($entry['halalas']),
                'file' => $entry['file'],
            ], $summary),
            'totals' => [
                'claims' => array_sum(array_column($summary, 'claims')),
                'transactions' => array_sum(array_column($summary, 'rows')),
                'total_sar' => $this->sar(array_sum(array_column($summary, 'halalas'))),
            ],
            'duration_seconds' => round(microtime(true) - $startedAt, 1),
        ];

        file_put_contents(
            $directory.'/manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, array<string, mixed>>  $summary
     */
    private function report(array $source, array $summary, string $directory): void
    {
        uasort($summary, fn ($a, $b) => $b['claims'] <=> $a['claims']);

        $rows = [];
        foreach ($summary as $scenario => $entry) {
            $rows[] = [
                in_array($scenario, RecoveryScenarioClassifier::APPLICABLE, true) ? "* {$scenario}" : $scenario,
                number_format($entry['claims']),
                number_format($entry['rows']),
                number_format($this->sar($entry['halalas']), 2),
            ];
        }

        $rows[] = new TableSeparator;
        $rows[] = [
            'TOTAL',
            number_format(array_sum(array_column($summary, 'claims'))),
            number_format(array_sum(array_column($summary, 'rows'))),
            number_format($this->sar(array_sum(array_column($summary, 'halalas'))), 2),
        ];

        $this->table(['Scenario', 'Claims', 'Transactions', 'SAR'], $rows);
        $this->line('* applicable to recovery:apply');

        if ($source['unparsable']) {
            $this->warn(count($source['unparsable']).' row(s) could not be parsed and were excluded.');
        }

        $this->newLine();
        $this->line('Output: '.$directory);
    }

    private function rule(string $scenario): string
    {
        return match ($scenario) {
            RecoveryScenarioClassifier::NOT_IN_RCP => 'No claim in RCP with this claim number.',
            RecoveryScenarioClassifier::AMBIGUOUS_CLAIM => 'Several RCP claims share this number and the policy number does not single one out.',
            RecoveryScenarioClassifier::NO_PAYMENT_ROW => 'Claim exists but has no payment row, so it cannot hold transactions.',
            RecoveryScenarioClassifier::MULTIPLE_PAYMENTS => 'Claim has more than one payment row.',
            RecoveryScenarioClassifier::HAS_ACTIVE_DISCOUNT => 'Claim has an active discount.',
            RecoveryScenarioClassifier::ALREADY_RECORDED => 'System collected total equals the workbook total; almost certainly the same money.',
            RecoveryScenarioClassifier::OVERLAP_UNCLEAR => 'System already holds collected money that does not equal the workbook total.',
            RecoveryScenarioClassifier::MULTIPLE_COMPLETE => 'More than one Complete settlement row.',
            RecoveryScenarioClassifier::STILL_COLLECTING => 'No Complete row and no system payment; collection is still in progress, not a discount.',
            RecoveryScenarioClassifier::OVER_COLLECTED => 'One Complete row, no system payment, workbook total exceeds the claim amount.',
            RecoveryScenarioClassifier::FULL => 'One Complete row, no system payment, workbook total equals the claim amount.',
            RecoveryScenarioClassifier::DISCOUNT => 'One Complete row, no system payment, workbook total below the claim amount. Discount implied but out of scope.',
            default => $scenario,
        };
    }

    private function sar(?int $halalas): float
    {
        return round(((int) $halalas) / 100, 2);
    }
}
