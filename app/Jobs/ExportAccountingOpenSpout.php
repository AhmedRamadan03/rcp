<?php

namespace App\Jobs;

use App\Enums\DataHistoryEnum;
use App\Enums\ExportType;
use App\Enums\Roles;
use App\Exceptions\ExportCancelledException;
use App\Exports\OpenSpout\AccountingOpenSpoutExport;
use App\Exports\OpenSpout\ClaimOpenSpoutExport;
use App\Models\DataHistory;
use App\Services\ClaimService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Queued job that exports claims to XLSX using OpenSpout.
 *
 * Designed for large datasets in constrained environments:
 *  - Database queue driver compatible (all properties are serialisable).
 *  - Memory-safe: OpenSpout streams to disk; DB rows are chunked.
 */
class   ExportAccountingOpenSpout implements ShouldQueue
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

    protected array  $filters;
    protected int    $userId;
    protected int    $roleId;
    protected ?int   $clientId;
    protected string $fileName;
    protected string $historyRef;
    protected array $columns;



    /**
     * @param  array   $filters   The same filter array used by ClaimService.
     * @param  object  $user      Authenticated user (will be decomposed for safe serialisation).
     * @param  string  $fileName  Target file name inside storage/app/public/exports/.
     */
    public function __construct(array $filters, object $user, string $fileName,   $historyRef = null, array $columns = [])
    {
        $this->filters  = $filters;
        $this->userId   = $user->id;
        $this->roleId   = $user->role_id;
        $this->clientId = $user->client_id ?? null;
        $this->fileName = $fileName;
        $this->historyRef = $historyRef;
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
        $user = \App\Models\User::findOrFail($this->userId);

        $claimService = new ClaimService();
        $queryBuilder = $claimService->listExportForAccount($this->filters, false, 10, true, $user);
        


        // Ensure the exports directory exists on the public disk. 
        Storage::disk('public')->makeDirectory('exports');

        $absolutePath = Storage::disk('public')->path('exports/' . $this->fileName);
        try {
            AccountingOpenSpoutExport::write($queryBuilder, $absolutePath, chunkSize: 2000,  onChunk: fn() => abortIfCancelled($this->historyRef), userColumnsOrder: $this->columns);


            // Notify the user that the file is ready for download.
            MarkExportAsReady::dispatch($this->fileName, $user, ExportType::EXPORT_ACCOUNTING_DATA, $this->historyRef);
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
        \Log::error('OpenSpout Accounting export failed', [
            'user_id'  => $this->userId,
            'fileName' => $this->fileName,
            'error'    => $exception?->getMessage(),
        ]);
    }
}
