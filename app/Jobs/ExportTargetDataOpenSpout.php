<?php

namespace App\Jobs;

use App\Enums\ExportType;
use App\Exceptions\ExportCancelledException;
use App\Exports\OpenSpout\CollectorTargetsOpenSpoutExport;
use App\Exports\OpenSpout\TargetDataOpenSpoutExport;
use App\Services\TargetReportService;
use App\Services\TargetService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Queued job that exports claims to XLSX using OpenSpout.
 *
 * Designed for large datasets in constrained environments:
 *  - Database queue driver compatible (all properties are serialisable).
 *  - Memory-safe: OpenSpout streams to disk; DB rows are chunked.
 */
class   ExportTargetDataOpenSpout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of seconds the job can run before timing out.
     * Set generously for very large exports.
     */
    public int $timeout = 900; // 15 minutes

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * Delete the job if its models no longer exist (soft-deleted user, etc.).
     */
    public bool $deleteWhenMissingModels = true;

    protected object $user;
    protected int $target_id;
    protected string $fileName;
    protected string $historyRef;
    protected array $filters;
    protected array $columns;



    /**
     * @param  array   $filters   The same filter array used by ClaimService.
     * @param  object  $user      Authenticated user (will be decomposed for safe serialisation).
     * @param  string  $fileName  Target file name inside storage/app/public/exports/.
     * @param  string  $historyRef Optional reference to a DataHistory record for progress tracking.
     * @param  array   $columns   The columns to include in the export.
     */
    public function __construct( object $user, int $target_id, string $fileName, $historyRef = null, array $filters = [], array $columns = [])
    {
        $this->user   = $user;
        $this->target_id = $target_id;
        $this->fileName = $fileName;
        $this->historyRef = $historyRef;
        $this->filters = $filters;
        $this->columns = $columns;

        // Dedicated queue so heavy exports don't block lighter jobs.
        $this->onQueue('exports');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Rebuild a minimal user object for ClaimService (avoids serialising the full model).

        $targetReportService = new TargetReportService();
        

       $targets = $targetReportService->getTargetData($this->target_id, null, true, $this->filters);

        Storage::disk('public')->makeDirectory('exports');

        $absolutePath = Storage::disk('public')->path('exports/' . $this->fileName);
        try {
            TargetDataOpenSpoutExport::write($targets, $absolutePath, chunkSize: 2000,  onChunk: fn() => abortIfCancelled($this->historyRef), userColumnsOrder: $this->columns);

            // Notify the user that the file is ready for download.
            MarkExportAsReady::dispatch($this->fileName, $this->user, ExportType::EXPORT_TARGET_DATA, $this->historyRef);
        } catch (ExportCancelledException) {
            // المستخدم ألغى التصدير — نحذف الملف الناقص ونخرج بهدوء
            Storage::disk('public')->delete('exports/' . $this->fileName);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        \Log::error('OpenSpout target export failed', [
            'user_id'  => $this->user->id ?? null,
            'fileName' => $this->fileName,
            'error'    => $exception?->getMessage(),
        ]);
    }

  
}
