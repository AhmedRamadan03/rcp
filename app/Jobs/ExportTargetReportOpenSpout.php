<?php

namespace App\Jobs;

use App\Enums\DataHistoryEnum;
use App\Enums\ExportType;
use App\Enums\Roles;
use App\Exceptions\ExportCancelledException;
use App\Exports\OpenSpout\ClaimOpenSpoutExport;
use App\Exports\OpenSpout\TargetOpenSpoutExport;
use App\Exports\OpenSpout\TargetReportOpenSpoutExport;
use App\Models\DataHistory;
use App\Services\ClaimService;
use App\Services\TargetReportService;
use App\Services\UserService;
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
class   ExportTargetReportOpenSpout implements ShouldQueue
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
    public function __construct( object $user, string $fileName, $historyRef = null, array $filters = [], array $columns = [])
    {
        $this->user   = $user;
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
        

       $targets = $targetReportService->getCollectorsWithAllData(null, $this->user, true, $this->filters);
        // حول الاستعلام الأصلي لـ subquery
        Storage::disk('public')->makeDirectory('exports');

        $absolutePath = Storage::disk('public')->path('exports/' . $this->fileName);
        try {
            TargetReportOpenSpoutExport::write($targets, $absolutePath, chunkSize: 2000,  onChunk: fn() => abortIfCancelled($this->historyRef), userColumnsOrder: $this->columns);


            // Notify the user that the file is ready for download.
            MarkExportAsReady::dispatch($this->fileName, $this->user, ExportType::EXPORT_TARGET_REPORT, $this->historyRef);
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
