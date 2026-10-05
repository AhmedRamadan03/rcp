<?php

namespace App\Jobs;


use App\Enums\ExportType;
use App\Exports\OpenSpout\DataHistoryOpenSpoutExport;
use App\Exports\OpenSpout\IvrOpenSpoutExport;
use App\Models\DataHistory;
use App\Models\Ivr;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

/**
 * Queued job that exports claims to XLSX using OpenSpout.
 *
 * Designed for large datasets in constrained environments:
 *  - Database queue driver compatible (all properties are serialisable).
 *  - Memory-safe: OpenSpout streams to disk; DB rows are chunked.
 */
class ExportImportDataOpenSpout implements ShouldQueue
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
    protected array  $filters;
    protected array  $columns;

    /**
     * @param  object  $user      Authenticated user (will be decomposed for safe serialisation).
     * @param  string  $fileName  Target file name inside storage/app/public/exports/.
     */
    public function __construct(object $user, string $fileName, $historyRef = null, array $filters = [], array $columns = [])
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

        $queryBuilder = $this->filterData(DataHistory::query());

        // Ensure the exports directory exists on the public disk.
        Storage::disk('public')->makeDirectory('exports');

        $absolutePath = Storage::disk('public')->path('exports/' . $this->fileName);

        DataHistoryOpenSpoutExport::write($queryBuilder, $absolutePath, chunkSize: 2000, columns: $this->columns);

        // Notify the user that the file is ready for download.
        MarkExportAsReady::dispatch($this->fileName, $this->user, ExportType::EXPORT_IMPORT_REQUESTS, $this->historyRef);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        \Log::error('OpenSpout IVR export failed', [
            'user_id'  => $this->user->id,
            'fileName' => $this->fileName,
            'error'    => $exception?->getMessage(),
        ]);
    }


     private function filterData($queryBuilder)
    {
        
        if (isset($this->filters['request_no'])) {
            $queryBuilder->where('ref', 'ILIKE', '%' . $this->filters['request_no'] . '%');
        }
        if (isset($this->filters['request_name'])) {
            $queryBuilder->where('title', 'ILIKE', '%' . $this->filters['request_name'] . '%');
        }
        if (isset($this->filters['type'])) {
            $queryBuilder->whereIn('type', Arr::wrap($this->filters['type']));
        }
        if (isset($this->filters['ids'])) {
            $queryBuilder->whereIn('id', Arr::wrap($this->filters['ids']));
        }
        if (isset($this->filters['status'])) {
            $queryBuilder->whereIn('status', Arr::wrap($this->filters['status']));
        }
        if (isset($this->filters['request_at_from']) && isset($this->filters['request_at_to'])) {
            $queryBuilder->whereBetween('created_at', [$this->filters['request_at_from'],$this->filters['request_at_to']]);
            
        }
        if (isset($this->filters['replied_at_from']) && isset($this->filters['replied_at_to'])) {
            $queryBuilder->whereHas('notification', function ($query) {
                $query->whereBetween('updated_at', [$this->filters['replied_at_from'],$this->filters['replied_at_to']]);

            });
        }
        if (isset($this->filters['search'])) {
            $queryBuilder->where(function ($query)  {
                    $query->where('ref', 'ILIKE', '%' . $this->filters['search'] . '%')
                    ->orWhere('title', 'ILIKE', '%' . $this->filters['search'] . '%')
                    ;
                });
            
        }

        return $queryBuilder;

    }
}
