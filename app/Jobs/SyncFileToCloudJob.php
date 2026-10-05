<?php

namespace App\Jobs;

use App\Models\File as FileModel;
use App\Services\FilesUploader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mirrors a single local-stored File to its target GCS bucket. Reused by
 * FilesUploader::registerLocalFile() (for Puppeteer / Excel::store flows)
 * and by the files:sync-to-cloud command for one-shot backfills.
 *
 * Idempotent: if the File row is already synced the job no-ops.
 */
class SyncFileToCloudJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /**
     * Exponential backoff between retries (seconds).
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 60, 120, 300, 600];
    }

    public function __construct(
        public int $fileId,
        public bool $deleteLocal = false,
    ) {}

    public function handle(FilesUploader $uploader): void
    {
        $file = FileModel::find($this->fileId);
        if ($file === null) {
            Log::warning('SyncFileToCloudJob: File row missing', ['file_id' => $this->fileId]);

            return;
        }

        try {
            $uploader->syncToCloud($file, $this->deleteLocal);
        } catch (Throwable $e) {
            Log::warning('SyncFileToCloudJob failed', [
                'file_id' => $this->fileId,
                'error'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
