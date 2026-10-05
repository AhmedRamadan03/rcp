<?php

namespace App\Console\Commands;

use App\Enums\FileSource;
use App\Enums\StorageDriver;
use App\Models\AdditionalFile;
use App\Models\ClaimFile;
use App\Models\ClaimStatusHistory;
use App\Models\Discharge;
use App\Models\File as FileModel;
use App\Models\RejectedClaim;
use App\Models\UploadedClaimsFile;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File as FileFacade;
use Throwable;

/**
 * Creates a corresponding row in the new `files` table for every path, and writes the FK back onto the domain
 * tables. Idempotent: a row is touched only if its FK is still null.
 *
 * The application is not yet reading these FKs.
 */
class BackfillFiles extends Command
{
    protected $signature = 'files:backfill
        {--table=all : Which domain to backfill (claim_files, additional_files, uploaded_claims_files, vouchers, rejected_claims, discharges, claim_status_histories, users, all)}
        {--chunk=500 : Rows per chunk}
        {--dry-run : Plan only — print counts and skip writes}';

    protected $description = 'Backfill the central files table from the eight legacy file-path columns.';

    /**
     * Per-table running stats. Each entry is a fresh array set up in
     * resetStats() at the start of every domain processor so the summary
     * can be aggregated at the end.
     */
    private array $stats = [];

    private const TABLES = [
        'claim_files',
        'additional_files',
        'uploaded_claims_files',
        'vouchers',
        'rejected_claims',
        'discharges',
        'claim_status_histories',
        'users',
    ];

    public function handle(): int
    {
        $table  = $this->option('table');
        $chunk  = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        if ($table !== 'all' && ! in_array($table, self::TABLES, true)) {
            $this->error("Unknown --table value: {$table}");
            $this->line('Valid: ' . implode(', ', array_merge(['all'], self::TABLES)));
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('Dry-run mode — no rows will be written.');
        }

        $targets = $table === 'all' ? self::TABLES : [$table];

        foreach ($targets as $t) {
            $this->info("\n→ Backfilling {$t}");
            $this->resetStats($t);

            try {
                match ($t) {
                    'claim_files'            => $this->backfillClaimFiles($chunk, $dryRun),
                    'additional_files'       => $this->backfillAdditionalFiles($chunk, $dryRun),
                    'uploaded_claims_files'  => $this->backfillUploadedClaimsFiles($chunk, $dryRun),
                    'vouchers'               => $this->backfillVouchers($chunk, $dryRun),
                    'rejected_claims'        => $this->backfillRejectedClaims($chunk, $dryRun),
                    'discharges'             => $this->backfillDischarges($chunk, $dryRun),
                    'claim_status_histories' => $this->backfillClaimStatusHistories($chunk, $dryRun),
                    'users'                  => $this->backfillUsersProfileImage($chunk, $dryRun),
                };
            } catch (Throwable $e) {
                $this->error("Failed during {$t}: " . $e->getMessage());
                return self::FAILURE;
            }

            $this->renderStats($t);
        }

        return self::SUCCESS;
    }

    /* ===================== Domain processors ===================== */

    private function backfillClaimFiles(int $chunkSize, bool $dryRun): void
    {
        ClaimFile::query()
            ->whereNull('file_id')
            ->whereNotNull('url')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use ($dryRun) {
                $this->runChunk('claim_files', $dryRun, function () use ($rows) {
                    foreach ($rows as $row) {
                        $file = $this->createLocalFile($row->url, null, FileSource::CLAIM_FILES);
                        $this->stats['claim_files']['created']++;
                        $row->forceFill(['file_id' => $file->id])->save();
                    }
                });
            });
    }

    private function backfillAdditionalFiles(int $chunkSize, bool $dryRun): void
    {
        AdditionalFile::query()
            ->whereNull('file_id')
            ->whereNotNull('url')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use ($dryRun) {
                $this->runChunk('additional_files', $dryRun, function () use ($rows) {
                    foreach ($rows as $row) {
                        $file = $this->createLocalFile($row->url, $row->uploaded_by, FileSource::ADDITIONAL_FILES);
                        $this->stats['additional_files']['created']++;
                        $row->forceFill(['file_id' => $file->id])->save();
                    }
                });
            });
    }

    private function backfillUploadedClaimsFiles(int $chunkSize, bool $dryRun): void
    {
        $needsWork = function (Builder $q): Builder {
            return $q->where(function (Builder $q2) {
                $q2->where(function (Builder $q3) {
                    $q3->whereNull('original_file_id')->whereNotNull('file_url');
                })->orWhere(function (Builder $q3) {
                    $q3->whereNull('failed_file_id')->whereNotNull('failed_file_path');
                });
            });
        };

        $needsWork(UploadedClaimsFile::query())
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use ($dryRun) {
                $this->runChunk('uploaded_claims_files', $dryRun, function () use ($rows) {
                    foreach ($rows as $row) {
                        $updates = [];

                        if (is_null($row->original_file_id) && filled($row->file_url)) {
                            $file = $this->createLocalFile($row->file_url, $row->user_id, FileSource::UPLOADED_CLAIMS_FILES);
                            $updates['original_file_id'] = $file->id;
                            $this->stats['uploaded_claims_files']['created']++;
                        }

                        if (is_null($row->failed_file_id) && filled($row->failed_file_path)) {
                            $file = $this->createLocalFile($row->failed_file_path, $row->user_id, FileSource::UPLOADED_CLAIMS_FILES);
                            $updates['failed_file_id'] = $file->id;
                            $this->stats['uploaded_claims_files']['created']++;
                        }

                        if (! empty($updates)) {
                            $row->forceFill($updates)->save();
                        }
                    }
                });
            });
    }

    private function backfillVouchers(int $chunkSize, bool $dryRun): void
    {
        Voucher::query()
            ->whereNull('file_id')
            ->whereNotNull('url')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use ($dryRun) {
                $this->runChunk('vouchers', $dryRun, function () use ($rows) {
                    foreach ($rows as $row) {
                        $file = $this->createLocalFile($row->url, null, FileSource::VOUCHERS);
                        $this->stats['vouchers']['created']++;
                        $row->forceFill(['file_id' => $file->id])->save();
                    }
                });
            });
    }

    private function backfillRejectedClaims(int $chunkSize, bool $dryRun): void
    {
        // We can't filter on JSON cheaply across drivers, so we pull every
        // row whose pivot is empty and inspect its JSON in PHP.
        RejectedClaim::query()
            ->whereDoesntHave('attachments')
            ->whereNotNull('files')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use ($dryRun) {
                $this->runChunk('rejected_claims', $dryRun, function () use ($rows) {
                    foreach ($rows as $row) {
                        $paths = $this->decodeRejectedClaimPaths($row->files);

                        if (empty($paths)) {
                            $this->stats['rejected_claims']['skipped_invalid_json']++;
                            continue;
                        }

                        foreach ($paths as $path) {
                            if (! is_string($path) || $path === '') {
                                continue;
                            }
                            $file = $this->createLocalFile($path, null, FileSource::REJECTED_CLAIMS);
                            $row->attachments()->attach($file->id, ['file_path' => $path]);
                            $this->stats['rejected_claims']['created']++;
                        }
                    }
                });
            });
    }

    private function backfillDischarges(int $chunkSize, bool $dryRun): void
    {
        Discharge::query()
            ->whereNull('file_id')
            ->whereNotNull('url')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use ($dryRun) {
                $this->runChunk('discharges', $dryRun, function () use ($rows) {
                    foreach ($rows as $row) {
                        $file = $this->createLocalFile($row->url, null, FileSource::DISCHARGE_FILES);
                        $this->stats['discharges']['created']++;
                        $row->forceFill(['file_id' => $file->id])->save();
                    }
                });
            });
    }

    private function backfillClaimStatusHistories(int $chunkSize, bool $dryRun): void
    {
        ClaimStatusHistory::query()
            ->whereNull('file_id')
            ->whereNotNull('file')
            ->where('file', '!=', '')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use ($dryRun) {
                $this->runChunk('claim_status_histories', $dryRun, function () use ($rows) {
                    foreach ($rows as $row) {
                        $file = $this->createLocalFile($row->file, $row->user_id, FileSource::CLAIM_STATUS_HISTORY_FILES);
                        $this->stats['claim_status_histories']['created']++;
                        $row->forceFill(['file_id' => $file->id])->save();
                    }
                });
            });
    }

    private function backfillUsersProfileImage(int $chunkSize, bool $dryRun): void
    {
        User::query()
            ->whereNull('profile_image_file_id')
            ->whereNotNull('profile_image')
            ->where('profile_image', '!=', '')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use ($dryRun) {
                $this->runChunk('users', $dryRun, function () use ($rows) {
                    foreach ($rows as $row) {
                        $file = $this->createLocalFile($row->profile_image, $row->id, FileSource::PROFILE_IMAGES);

                        $this->stats['users']['created']++;
                        $row->forceFill(['profile_image_file_id' => $file->id])->save();
                    }
                });
            });
    }

    /* ===================== Helpers ===================== */

    /**
     * Create a `files` row for a local path. Tries to stat the underlying
     * file via the same fallback the /show-file route uses
     * (storage/app/public/ then storage/app/) so we can fill in mime.
     * A missing file is logged and we still create the row so the FK is
     * always satisfied — Phase 2 verification will surface these.
     */
    private function createLocalFile(string $path, ?int $uploadedBy, FileSource $source): FileModel
    {
        $relative = ltrim($path, '/');
        $resolved = $this->resolveLocalPath($relative);
        $mimeType = null;

        if ($resolved !== null) {
            try {
                $mimeType = FileFacade::mimeType($resolved) ?: null;
            } catch (Throwable $e) {
                $mimeType = null;
            }
        } else {
            $this->stats[$this->currentTable()]['missing_on_disk']++;
        }

        return FileModel::create([
            'original_filename' => basename($relative),
            'mime_type'         => $mimeType,
            'local_path'        => $path,
            'cloud_path'        => null,
            'storage_driver'    => StorageDriver::LOCAL->value,
            'bucket_config'   => null,
            'synced_at'       => null,
            'uploaded_by'       => $uploadedBy,
            'source'            => $source->value,
            'synced_to_cloud'   => false,
        ]);
    }

    private function resolveLocalPath(string $relative): ?string
    {
        $candidates = [
            storage_path('app/public/' . $relative),
            storage_path('app/' . $relative),
        ];

        foreach ($candidates as $candidate) {
            if (FileFacade::exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The rejected_claims.files column lives without a model cast, so we
     * decode defensively: it can be a JSON array string, a json_encode'd
     * scalar, or already a PHP array depending on write path / history.
     */
    private function decodeRejectedClaimPaths(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        return [];
    }

    /**
     * Wrap a chunk in a transaction unless we're dry-running.
     */
    private function runChunk(string $table, bool $dryRun, callable $work): void
    {
        if ($dryRun) {
            // Stat-only pass: we still want counts, so we run inside a
            // rolled-back transaction.
            DB::beginTransaction();
            try {
                $work();
            } finally {
                DB::rollBack();
            }
            return;
        }

        DB::transaction($work);
    }

    private function currentTable(): string
    {
        // Stack is built per-domain in handle(); we always have exactly one
        // active table when createLocalFile() is called.
        return array_key_last($this->stats) ?? 'unknown';
    }

    private function resetStats(string $table): void
    {
        $this->stats[$table] = [
            'created'              => 0,
            'missing_on_disk'      => 0,
            'skipped_invalid_json' => 0,
        ];
    }

    private function renderStats(string $table): void
    {
        $s = $this->stats[$table];

        $this->line(sprintf(
            '   created=%d  missing_on_disk=%d%s',
            $s['created'],
            $s['missing_on_disk'],
            $table === 'rejected_claims'
                ? sprintf('  skipped_invalid_json=%d', $s['skipped_invalid_json'])
                : ''
        ));
    }
}
