<?php

namespace App\Console\Commands;

use App\Enums\ClaimStatus;
use App\Models\Claim;
use App\Models\CloseClaim;
use App\Models\CloseReason;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

class UpdateClaimStatusCommand extends Command
{
    private const CLOSE_REASON = 'Closed by the system';

    private const SAMPLE_LIMIT = 5;

    private const DEFAULT_CHUNK_SIZE = 500;

    protected $signature = 'app:update-claim-status
        {filename=update-status.xlsx : XLSX file in public/}
        {--execute : Apply changes. Without this flag, dry-run only.}
        {--chunk='.self::DEFAULT_CHUNK_SIZE.' : Number of rows to load and write per batch}';

    protected $description = 'Bulk update claim statuses from an XLSX file (dry-run by default; pass --execute to apply)';

    private int $skipped = 0;

    private int $wouldCancel = 0;

    private int $wouldClose = 0;

    private int $notFound = 0;

    private int $applied = 0;

    private int $processedRows = 0;

    /** @var list<string> */
    private array $errors = [];

    /** @var list<string> */
    private array $skippedSamples = [];

    /** @var list<string> */
    private array $wouldCancelSamples = [];

    /** @var list<string> */
    private array $wouldCloseSamples = [];

    /** @var list<string> */
    private array $notFoundSamples = [];

    public function handle(): int
    {
        $filename = $this->argument('filename');
        $filePath = public_path($filename);
        $execute = (bool) $this->option('execute');
        $chunkSize = max(1, (int) $this->option('chunk'));

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filename}");

            return self::FAILURE;
        }

        if ($execute) {
            $this->warn('Running in EXECUTE mode. Claim statuses will be updated.');
        } else {
            $this->info('Running in DRY-RUN mode. No changes will be made. Pass --execute to apply.');
        }

        $this->line("Processing in batches of {$chunkSize} rows.");

        $systemUser = User::where('email', 'hazim@taheiya.sa')->first(['id']);
        if ($execute && ! $systemUser) {
            $this->error('System user hazim@taheiya.sa not found. Run seeders first.');

            return self::FAILURE;
        }

        $closeReason = null;
        if ($execute) {
            $closeReason = CloseReason::firstOrCreate(['reason' => self::CLOSE_REASON]);
        }

        $reader = new Reader();
        $reader->open($filePath);

        $headers = [];
        $rowBuffer = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $this->processedRows++;
                $cells = array_map(fn ($cell) => $cell->getValue(), $row->getCells());

                if ($this->processedRows === 1) {
                    $headers = $this->normalizeHeaders($cells);
                    continue;
                }

                if ($headers === []) {
                    continue;
                }

                $cells = $this->padCells($cells, count($headers));
                $rowBuffer[] = [
                    'index' => $this->processedRows,
                    'data' => array_combine($headers, $cells),
                ];

                if (count($rowBuffer) >= $chunkSize) {
                    $this->processChunk($rowBuffer, $execute, $systemUser, $closeReason);
                    $rowBuffer = [];
                    $this->reportProgress();
                }
            }
            break;
        }

        if ($rowBuffer !== []) {
            $this->processChunk($rowBuffer, $execute, $systemUser, $closeReason);
            $this->reportProgress();
        }

        $this->newLine();

        $reader->close();

        $this->printSummary($execute);

        return empty($this->errors) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<array{index: int, data: array<string, mixed>}>  $rows
     */
    private function processChunk(
        array $rows,
        bool $execute,
        ?User $systemUser,
        ?CloseReason $closeReason
    ): void {
        $parsedRows = [];
        $claimIds = [];

        foreach ($rows as $row) {
            $claimId = (int) ($row['data']['claim_id'] ?? 0);
            $claimNumber = trim((string) ($row['data']['claim_number'] ?? ''));

            if (! $claimId || $claimNumber === '') {
                $this->recordError("Row {$row['index']}: missing claim_id or claim_number.");

                continue;
            }

            $parsedRows[] = [
                'index' => $row['index'],
                'claim_id' => $claimId,
                'claim_number' => $claimNumber,
            ];
            $claimIds[] = $claimId;
        }

        if ($parsedRows === []) {
            return;
        }

        $claims = Claim::query()
            ->without(['city', 'client'])
            ->whereIn('id', array_unique($claimIds))
            ->get(['id', 'claim_number', 'status'])
            ->keyBy(fn (Claim $claim) => $this->claimKey($claim->id, $claim->claim_number));

        $toCloseIds = [];
        $toCancelIds = [];
        $closeClaimRows = [];
        $historyRows = [];
        $pendingUpdates = [];

        foreach ($parsedRows as $parsedRow) {
            $key = $this->claimKey($parsedRow['claim_id'], $parsedRow['claim_number']);
            $claim = $claims->get($key);

            if (! $claim) {
                $this->notFound++;
                $this->pushSample(
                    $this->notFoundSamples,
                    "Row {$parsedRow['index']}: id={$parsedRow['claim_id']}, number={$parsedRow['claim_number']}"
                );

                continue;
            }

            $targetStatus = $this->resolveTargetStatus($claim->status);

            if ($targetStatus === null) {
                $this->skipped++;
                $label = $this->statusLabel($claim->status);
                $this->pushSample(
                    $this->skippedSamples,
                    "Row {$parsedRow['index']}: id={$parsedRow['claim_id']} ({$label})"
                );

                continue;
            }

            $fromLabel = $this->statusLabel($claim->status);
            $toLabel = $this->statusLabel($targetStatus);

            if ($targetStatus === ClaimStatus::REJECTED->value) {
                $this->wouldCancel++;
                $this->pushSample(
                    $this->wouldCancelSamples,
                    "Row {$parsedRow['index']}: id={$parsedRow['claim_id']} {$fromLabel} -> {$toLabel}"
                );
                $toCancelIds[] = $claim->id;
            } else {
                $this->wouldClose++;
                $this->pushSample(
                    $this->wouldCloseSamples,
                    "Row {$parsedRow['index']}: id={$parsedRow['claim_id']} {$fromLabel} -> {$toLabel}"
                );
                $toCloseIds[] = $claim->id;
            }

            if (! $execute) {
                continue;
            }

            $pendingUpdates[] = [
                'index' => $parsedRow['index'],
                'claim_id' => $claim->id,
                'previous_status' => $claim->status,
                'target_status' => $targetStatus,
            ];
        }

        if (! $execute || $pendingUpdates === []) {
            return;
        }

        $now = Carbon::now();

        foreach ($pendingUpdates as $update) {
            $closeClaimRows[] = [
                'claim_id' => $update['claim_id'],
                'reason_id' => $closeReason->id,
                'url' => 'bulk_status_update',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $historyRows[] = [
                'claim_id' => $update['claim_id'],
                'previous_status' => $update['previous_status'],
                'current_status' => $update['target_status'],
                'user_id' => $systemUser->id,
                'note' => self::CLOSE_REASON,
                'reason' => self::CLOSE_REASON,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        try {
            DB::transaction(function () use ($toCloseIds, $toCancelIds, $closeClaimRows, $historyRows) {
                if ($toCloseIds !== []) {
                    Claim::query()
                        ->without(['city', 'client'])
                        ->whereIn('id', array_unique($toCloseIds))
                        ->update([
                            'status' => ClaimStatus::CLOSED->value,
                            'is_close' => 1,
                        ]);
                }

                if ($toCancelIds !== []) {
                    Claim::query()
                        ->without(['city', 'client'])
                        ->whereIn('id', array_unique($toCancelIds))
                        ->update([
                            'status' => ClaimStatus::REJECTED->value,
                            'is_close' => 1,
                        ]);
                }

                foreach (array_chunk($closeClaimRows, self::DEFAULT_CHUNK_SIZE) as $chunk) {
                    CloseClaim::query()->insert($chunk);
                }

                foreach (array_chunk($historyRows, self::DEFAULT_CHUNK_SIZE) as $chunk) {
                    DB::table('claim_status_histories')->insert($chunk);
                }
            });

            $this->applied += count($pendingUpdates);
        } catch (Throwable $e) {
            $this->applyChunkRowByRow($pendingUpdates, $closeReason, $systemUser, $e->getMessage());
        }
    }

    /**
     * @param  list<array{index: int, claim_id: int, previous_status: string, target_status: string}>  $pendingUpdates
     */
    private function applyChunkRowByRow(
        array $pendingUpdates,
        CloseReason $closeReason,
        User $systemUser,
        string $chunkError
    ): void {
        $this->warn("Batch write failed ({$chunkError}). Retrying affected rows individually.");

        foreach ($pendingUpdates as $update) {
            try {
                DB::transaction(function () use ($update, $closeReason, $systemUser) {
                    CloseClaim::query()->create([
                        'claim_id' => $update['claim_id'],
                        'reason_id' => $closeReason->id,
                        'url' => 'bulk_status_update',
                    ]);

                    Claim::query()
                        ->without(['city', 'client'])
                        ->whereKey($update['claim_id'])
                        ->update([
                            'status' => $update['target_status'],
                            'is_close' => 1,
                        ]);

                    DB::table('claim_status_histories')->insert([
                        'claim_id' => $update['claim_id'],
                        'previous_status' => $update['previous_status'],
                        'current_status' => $update['target_status'],
                        'user_id' => $systemUser->id,
                        'note' => self::CLOSE_REASON,
                        'reason' => self::CLOSE_REASON,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });

                $this->applied++;
            } catch (Throwable $e) {
                $this->recordError("Row {$update['index']}: id={$update['claim_id']} failed: {$e->getMessage()}");
            }
        }
    }

    private function resolveTargetStatus(string $currentStatus): ?string
    {
        if (in_array($currentStatus, [ClaimStatus::CLOSED->value, ClaimStatus::REJECTED->value], true)) {
            return null;
        }

        if ($currentStatus === ClaimStatus::APPROVED->value) {
            return ClaimStatus::REJECTED->value;
        }

        return ClaimStatus::CLOSED->value;
    }

    private function statusLabel(string $status): string
    {
        return ClaimStatus::getString($status)." ({$status})";
    }

    private function claimKey(int $id, string $claimNumber): string
    {
        return $id.'|'.$claimNumber;
    }

    private function normalizeHeaders(array $headers): array
    {
        return array_map(function ($header) {
            return strtolower(trim(str_replace([' ', '-', '/', '\\'], '_', (string) $header)));
        }, $headers);
    }

    private function padCells(array $cells, int $count): array
    {
        if (count($cells) >= $count) {
            return array_slice($cells, 0, $count);
        }

        return array_pad($cells, $count, null);
    }

    private function reportProgress(): void
    {
        $this->line('Processed '.max($this->processedRows - 1, 0).' rows...');
    }

    private function pushSample(array &$samples, string $line): void
    {
        if (count($samples) < self::SAMPLE_LIMIT) {
            $samples[] = $line;
        }
    }

    private function recordError(string $message): void
    {
        $this->errors[] = $message;
        $this->error($message);
    }

    private function printSummary(bool $execute): void
    {
        $this->line(str_repeat('-', 60));
        $this->info('Summary');
        $this->line("Processed rows: {$this->processedRows}");
        $this->line("Skipped (already closed/canceled): {$this->skipped}");
        $this->line("Would cancel (pending -> canceled): {$this->wouldCancel}");
        $this->line("Would close (other -> closed): {$this->wouldClose}");
        $this->line("Not found: {$this->notFound}");

        if ($execute) {
            $this->line("Applied: {$this->applied}");
        }

        if ($this->errors !== []) {
            $this->line('Errors: '.count($this->errors));
        }

        $this->printSamples('Skipped samples', $this->skippedSamples);
        $this->printSamples('Would cancel samples', $this->wouldCancelSamples);
        $this->printSamples('Would close samples', $this->wouldCloseSamples);
        $this->printSamples('Not found samples', $this->notFoundSamples);

        if (! $execute) {
            $this->comment('Dry run only — no changes made.');
        }
    }

    private function printSamples(string $title, array $samples): void
    {
        if ($samples === []) {
            return;
        }

        $this->newLine();
        $this->line($title.':');
        foreach ($samples as $sample) {
            $this->line("  - {$sample}");
        }
    }
}
