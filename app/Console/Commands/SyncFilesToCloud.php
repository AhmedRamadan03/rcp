<?php

namespace App\Console\Commands;

use App\Enums\FileSource;
use App\Enums\StorageDriver;
use App\Jobs\SyncFileToCloudJob;
use App\Models\File as FileModel;
use App\Services\FilesUploader;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Throwable;

/**
 * Migrate locally-stored files (storage_driver = 'local') to their
 * configured cloud bucket and flip storage_driver = 'cloud' on the row.
 *
 * After every upload Storage::exists() is consulted to verify the object
 * is reachable; only then is the File row mutated. Re-runs are safe — rows
 * already on GCS are skipped.
 */
class SyncFilesToCloud extends Command
{
    protected $signature = 'files:sync-to-cloud
        {--source= : Limit to a single FileSource (e.g. profile_images, claim_files). Defaults to every source.}
        {--chunk=200 : Rows per chunk}
        {--limit=0 : Maximum rows to process (0 = no limit)}
        {--delete-local : Delete the local copy after successful upload}
        {--queue : Dispatch SyncFileToCloudJob for each row instead of syncing inline}
        {--dry-run : Plan only — print counts and skip writes}';

    protected $description = 'Mirror local files into GCS, verify the upload, and flip storage_driver per File row.';

    public function handle(FilesUploader $uploader): int
    {
        $sourceOpt = $this->option('source');
        $chunk     = max(1, (int) $this->option('chunk'));
        $limit     = max(0, (int) $this->option('limit'));
        $dryRun    = (bool) $this->option('dry-run');
        $useQueue  = (bool) $this->option('queue');
        $deleteLcl = (bool) $this->option('delete-local');

        if ($dryRun) {
            $this->warn('Dry-run mode — no rows will be uploaded.');
        }

        $sources = $sourceOpt
            ? [$this->parseSource($sourceOpt)]
            : array_map(fn (FileSource $s) => $s->value, FileSource::cases());

        $totalScanned = 0;
        $totalSynced  = 0;
        $totalSkipped = 0;
        $totalFailed  = 0;
        $totalMissing = 0;

        foreach ($sources as $source) {
            $this->info("\n→ Syncing source = {$source}");

            $query = $this->buildQuery($source);
            $rowCount = (clone $query)->count();
            $this->line("  candidates: {$rowCount}");
            if ($rowCount === 0) {
                continue;
            }

            $processed = 0;
            $query->chunkById($chunk, function ($files) use (
                $uploader, $dryRun, $useQueue, $deleteLcl, $limit,
                &$totalScanned, &$totalSynced, &$totalSkipped, &$totalFailed, &$totalMissing, &$processed
            ) {
                foreach ($files as $file) {
                    if ($limit > 0 && $processed >= $limit) {
                        return false;
                    }
                    $processed++;
                    $totalScanned++;

                    if ($file->synced_to_cloud && $file->storage_driver === StorageDriver::CLOUD) {
                        $totalSkipped++;
                        continue;
                    }

                    if (blank($file->local_path)) {
                        $this->warn("  - File #{$file->id} has no local_path; skipped.");
                        $totalMissing++;
                        continue;
                    }

                    $localOk = $this->localExists($file->local_path);
                    if (! $localOk) {
                        $this->warn("  - File #{$file->id} local_path '{$file->local_path}' is missing on disk; skipped.");
                        $totalMissing++;
                        continue;
                    }

                    if ($dryRun) {
                        $totalSynced++;
                        continue;
                    }

                    try {
                        if ($useQueue) {
                            SyncFileToCloudJob::dispatch($file->id, $deleteLcl);
                        } else {
                            $uploader->syncToCloud($file, $deleteLcl);
                        }
                        $totalSynced++;
                    } catch (Throwable $e) {
                        $totalFailed++;
                        $this->error("  - File #{$file->id} failed: " . $e->getMessage());
                    }
                }
            });
        }

        $this->newLine();
        $this->info('=== Summary ===');
        $this->line("Scanned: {$totalScanned}");
        $this->line("Synced:  {$totalSynced}" . ($useQueue ? ' (queued)' : ''));
        $this->line("Skipped: {$totalSkipped} (already on GCS)");
        $this->line("Missing: {$totalMissing}");
        $this->line("Failed:  {$totalFailed}");

        return $totalFailed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function buildQuery(string $source): EloquentBuilder
    {
        return FileModel::query()
            ->where('source', $source)
            ->where(function (EloquentBuilder $q) {
                $q->where('storage_driver', StorageDriver::LOCAL->value)
                    ->orWhere('synced_to_cloud', false);
            })
            ->whereNotNull('local_path')
            ->orderBy('id');
    }

    private function parseSource(string $value): string
    {
        $candidate = FileSource::tryFrom($value);
        if ($candidate === null) {
            $this->error("Unknown source '{$value}'. Allowed: " . implode(', ', array_column(FileSource::cases(), 'value')));
            exit(self::FAILURE);
        }

        return $candidate->value;
    }

    private function localExists(string $relative): bool
    {
        $relative = ltrim($relative, '/');
        foreach ([
            storage_path('app/public/'.$relative),
            storage_path('app/'.$relative),
        ] as $candidate) {
            if (is_file($candidate)) {
                return true;
            }
        }

        return false;
    }
}
