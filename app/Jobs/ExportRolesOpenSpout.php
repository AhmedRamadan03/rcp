<?php

namespace App\Jobs;

use App\Enums\DataHistoryEnum;
use App\Enums\ExportType;
use App\Exceptions\ExportCancelledException;
use App\Exports\OpenSpout\RolesOpenSpoutExport;

use App\Models\Role;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Queued job that exports claims to XLSX using OpenSpout.
 *
 * Designed for large datasets in constrained environments:
 *  - Database queue driver compatible (all properties are serialisable).
 *  - Memory-safe: OpenSpout streams to disk; DB rows are chunked.
 */
class ExportRolesOpenSpout implements ShouldQueue
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
    protected array $columns;
    protected array $filters;

    /**
     * @param  object  $user      Authenticated user (will be decomposed for safe serialisation).
     * @param  string  $fileName  Target file name inside storage/app/public/exports/.
     */
    public function __construct(object $user, string $fileName, $historyRef = null, array $columns = [], array $filters = [])
    {
        $this->user   = $user;
        $this->fileName = $fileName;
        $this->historyRef = $historyRef;
        $this->columns = $columns;
        $this->filters = $filters;


        // Dedicated queue so heavy exports don't block lighter jobs.
        $this->onQueue('exports');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        // Rebuild a minimal user object for ClaimService (avoids serialising the full model).

        $queryBuilder = Role::filter($this->filters);

        // Ensure the exports directory exists on the public disk.
        Storage::disk('public')->makeDirectory('exports');

        $absolutePath = Storage::disk('public')->path('exports/' . $this->fileName);

        try {
            RolesOpenSpoutExport::write(
                $queryBuilder,
                $absolutePath,
                chunkSize: 2000,
                onChunk: fn() => abortIfCancelled($this->historyRef),
                userColumnsOrder: $this->columns

            );
    
            
            MarkExportAsReady::dispatch(
                $this->fileName,
                $this->user,
                ExportType::EXPORT_ROLES,
                $this->historyRef
            );
            } catch (ExportCancelledException) {
                // المستخدم ألغى التصدير — نحذف الملف الناقص ونخرج بهدوء
                Storage::disk('public')->delete('exports/' . $this->fileName);
                Log::info('roles export cancelled by user', [
                    'user_id'    => $this->user->id,
                    'historyRef' => $this->historyRef,
                ]);
            }
    }

    
   
    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        \Log::error('OpenSpout roles export failed', [
            'user_id'  => $this->user->id,
            'fileName' => $this->fileName,
            'historyRef' => $this->historyRef,
            'error'    => $exception?->getMessage(),
        ]);
    }

 
}
