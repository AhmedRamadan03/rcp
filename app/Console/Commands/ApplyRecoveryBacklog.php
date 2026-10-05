<?php

namespace App\Console\Commands;

use App\Services\RecoveryReconciliation\RecoveryBacklogWriter;
use App\Services\RecoveryReconciliation\RecoveryScenarioClassifier;
use App\Services\RecoveryReconciliation\RecoveryWorkbookReader;
use Illuminate\Console\Command;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Throwable;

/**
 * Applies exactly one scenario file produced by recovery:split.
 *
 * Performs no classification: what gets written is what the file says. The only
 * judgement it makes is whether to abort a claim because the database changed
 * since the split.
 */
class ApplyRecoveryBacklog extends Command
{
    protected $signature = 'recovery:apply
        {file : Path to a scenario file produced by recovery:split}
        {--limit= : Process at most this many claims}
        {--apply : Perform the writes (omit for a dry run)}
        {--output= : Report directory, defaults to storage/app/recovery-apply/<scenario>-<timestamp>}';

    protected $description = 'Apply one recovery backlog scenario file';

    public function handle(RecoveryWorkbookReader $reader, RecoveryBacklogWriter $writer): int
    {
        $file = $this->argument('file');

        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        try {
            $claims = $reader->readScenarioFile($file);
        } catch (Throwable $exception) {
            $this->error('Could not read the scenario file: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($claims === []) {
            $this->error('The scenario file has no transaction rows.');

            return self::FAILURE;
        }

        if ($problems = $this->validate($claims)) {
            $this->error('The scenario file is not usable:');
            foreach ($problems as $problem) {
                $this->line('  - '.$problem);
            }

            return self::FAILURE;
        }

        if ($limit = (int) $this->option('limit')) {
            $claims = array_slice($claims, 0, $limit, preserve_keys: true);
        }

        $scenario = reset($claims)[0]['scenario'];
        $transactions = array_sum(array_map('count', $claims));
        $apply = (bool) $this->option('apply');

        $this->line(sprintf(
            '%s: %s claims, %s transactions, SAR %s from scenario "%s".',
            $apply ? 'APPLYING' : 'Dry run',
            number_format(count($claims)),
            number_format($transactions),
            number_format($this->totalHalalas($claims) / 100, 2),
            $scenario,
        ));

        if ($apply && ! $this->confirm('This writes to the database. Continue?', ! $this->input->isInteractive())) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        $results = $apply
            ? $this->applyAll($writer, $claims)
            : $this->plan($claims);

        return $this->report($results, $scenario, $apply, $file);
    }

    /**
     * Reject a file we cannot trust before touching the database.
     *
     * @param  array<int, list<array<string, mixed>>>  $claims
     * @return list<string>
     */
    private function validate(array $claims): array
    {
        $problems = [];
        $scenarios = [];

        foreach ($claims as $claimId => $rows) {
            foreach ($rows as $row) {
                $scenarios[$row['scenario']] = true;

                if ($row['payment_id'] !== $rows[0]['payment_id']) {
                    $problems[] = "Claim {$claimId}: rows disagree on Payment ID.";
                }

                if ($row['collector_id'] === null) {
                    $problems[] = "Claim {$claimId} row {$row['file_row']}: Collector ID is empty.";
                }
            }

            $orders = array_column($rows, 'order');
            sort($orders);

            if ($orders !== range(1, count($rows))) {
                $problems[] = "Claim {$claimId}: Order must be 1..".count($rows).', got '.implode(',', $orders).'.';
            }

            if ($claimId === 0) {
                $problems[] = 'A row has an empty Claim ID.';
            }
        }

        $scenarios = array_keys($scenarios);

        if (count($scenarios) > 1) {
            $problems[] = 'File mixes scenarios: '.implode(', ', $scenarios).'. Apply one scenario at a time.';
        } elseif (! in_array($scenarios[0], RecoveryScenarioClassifier::APPLICABLE, true)) {
            $problems[] = sprintf(
                'Scenario "%s" is not applicable. Only %s may be applied.',
                $scenarios[0],
                implode(' and ', RecoveryScenarioClassifier::APPLICABLE),
            );
        }

        return array_slice(array_unique($problems), 0, 20);
    }

    /**
     * @param  array<int, list<array<string, mixed>>>  $claims
     * @return list<array<string, mixed>>
     */
    private function applyAll(RecoveryBacklogWriter $writer, array $claims): array
    {
        $bar = $this->output->createProgressBar(count($claims));
        $results = [];

        foreach ($claims as $claimId => $rows) {
            try {
                $result = $writer->apply($claimId, $rows);
            } catch (Throwable $exception) {
                $result = ['status' => 'failed', 'reason' => $exception->getMessage()];
            }

            $results[] = $result + ['claim_id' => $claimId, 'claim_number' => $rows[0]['claim_number']];
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        return $results;
    }

    /**
     * @param  array<int, list<array<string, mixed>>>  $claims
     * @return list<array<string, mixed>>
     */
    private function plan(array $claims): array
    {
        $results = [];

        foreach ($claims as $claimId => $rows) {
            $results[] = [
                'status' => 'planned',
                'claim_id' => $claimId,
                'claim_number' => $rows[0]['claim_number'],
                'transactions' => count($rows),
                'halalas' => array_sum(array_column($rows, 'amount')),
                'refs' => array_map(fn ($row) => RecoveryBacklogWriter::REF_PREFIX.$row['source_row'], $rows),
            ];
        }

        return $results;
    }

    /**
     * @param  list<array<string, mixed>>  $results
     */
    private function report(array $results, string $scenario, bool $apply, string $sourceFile): int
    {
        $directory = $this->option('output')
            ?: storage_path('app/recovery-apply/'.$scenario.'-'.now()->format('Y-m-d_His'));

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->error("Could not create output directory: {$directory}");

            return self::FAILURE;
        }

        $done = array_values(array_filter($results, fn ($r) => in_array($r['status'], ['applied', 'planned'], true)));
        $skipped = array_values(array_filter($results, fn ($r) => $r['status'] === 'skipped'));
        $failed = array_values(array_filter($results, fn ($r) => $r['status'] === 'failed'));

        $this->writeReport(
            $directory.'/'.($apply ? 'applied' : 'planned').'.xlsx',
            ['Claim NO.', 'Claim ID', 'Previous Status', 'Transactions', 'SAR', 'Transaction Refs'],
            array_map(fn ($r) => [
                $r['claim_number'], $r['claim_id'], $r['previous_status'] ?? '',
                $r['transactions'], round($r['halalas'] / 100, 2), implode(' ', $r['refs']),
            ], $done),
        );

        if ($skipped || $failed) {
            $this->writeReport(
                $directory.'/skipped.xlsx',
                ['Claim NO.', 'Claim ID', 'Outcome', 'Reason'],
                array_map(fn ($r) => [$r['claim_number'], $r['claim_id'], $r['status'], $r['reason']], [...$skipped, ...$failed]),
            );
        }

        $reasons = array_count_values(array_map(fn ($r) => $r['reason'], [...$skipped, ...$failed]));

        file_put_contents($directory.'/manifest.json', json_encode([
            'schema_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'scenario' => $scenario,
            'mode' => $apply ? 'apply' : 'dry-run',
            'scenario_file' => realpath($sourceFile),
            'scenario_file_sha256' => hash_file('sha256', $sourceFile),
            'claims_written' => count($done),
            'transactions_written' => array_sum(array_column($done, 'transactions')),
            'total_sar' => round(array_sum(array_column($done, 'halalas')) / 100, 2),
            'claims_skipped' => count($skipped),
            'claims_failed' => count($failed),
            'reasons' => $reasons,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->table(['Metric', 'Value'], [
            [$apply ? 'Claims applied' : 'Claims planned', number_format(count($done))],
            ['Transactions', number_format(array_sum(array_column($done, 'transactions')))],
            ['SAR', number_format(array_sum(array_column($done, 'halalas')) / 100, 2)],
            ['Claims skipped', number_format(count($skipped))],
            ['Claims failed', number_format(count($failed))],
        ]);

        foreach ($reasons as $reason => $count) {
            $this->line(sprintf('  %6s  %s', number_format($count), $reason));
        }

        $this->newLine();
        $this->line('Output: '.$directory);

        if (! $apply) {
            $this->newLine();
            $this->comment('Dry run — nothing was written. Re-run with --apply to perform the writes.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<mixed>>  $rows
     */
    private function writeReport(string $path, array $headers, array $rows): void
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($headers));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();
    }

    /**
     * @param  array<int, list<array<string, mixed>>>  $claims
     */
    private function totalHalalas(array $claims): int
    {
        return array_sum(array_map(fn ($rows) => array_sum(array_column($rows, 'amount')), $claims));
    }
}
