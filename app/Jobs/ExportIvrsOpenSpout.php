<?php

namespace App\Jobs;


use App\Enums\ExportType;
use App\Exceptions\ExportCancelledException;
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
class ExportIvrsOpenSpout implements ShouldQueue
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

    protected int    $userId;
    protected string $fileName;
    protected string $historyRef;
    protected array $columns;
    protected array $filters;

    /**
     * @param  object  $user      Authenticated user (will be decomposed for safe serialisation).
     * @param  string  $fileName  Target file name inside storage/app/public/exports/.
     */
    public function __construct(object $user, string $fileName, $historyRef = null,  array $columns = [], array $filters = [])
    {
        $this->userId   = $user->id;
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
        $user = \App\Models\User::findOrFail($this->userId);

        $queryBuilder = Ivr::with('paymentLink.payment.claim');
        if (isset($this->filters['call_date_from']) && isset($this->filters['call_date_to'])) {
            $queryBuilder->whereBetween('created_at', [$this->filters['call_date_from'],$this->filters['call_date_to']]); 
        }
        if (isset($this->filters['created_at_from']) && isset($this->filters['created_at_to'])) {
            $queryBuilder->whereBetween('created_at', [$this->filters['created_at_from'],$this->filters['created_at_to']]); 
        }
        if (isset($this->filters['ids'])) {
            $queryBuilder->whereIn('id', Arr::wrap($this->filters['ids'])); 
        }
        if (isset($this->filters['call_status'])) {
            $queryBuilder->whereIn('status', Arr::wrap($this->filters['call_status'])); 
        }
        if (isset($this->filters['status'])) {
            $queryBuilder->whereIn('status', Arr::wrap($this->filters['status'])); 
        }
        if (isset($this->filters['debtor_phone'])) {
            $queryBuilder->whereHas('paymentLink.payment.claim', function ($q) {
                $q->where('debtor_phone', 'ILIKE', '%' . $this->filters['debtor_phone'] . '%');
            });
        }
        
        if (isset($this->filters['claim_number'])) {
            $queryBuilder->whereHas('paymentLink.payment.claim', function ($q) {
                $q->where('claim_number', 'ILIKE', '%' . $this->filters['claim_number'] . '%');
            });
        }
         if (isset($this->filters['search'])) {
            $queryBuilder->where(function($q){
                $q->whereHas('paymentLink.payment.claim', function ($q2)  {
                    $q2->where('claim_number', 'ILIKE', '%' . $this->filters['search'] . '%')
                    ->orWhere('debtor_phone', 'ILIKE', '%' . $this->filters['search'] . '%');;
                });
                
            
            });
        }

        $queryBuilder->select([
                'id',
                'payment_link_id',
                'retry_number',
                'status',
                'created_at',
            ]);
        // Ensure the exports directory exists on the public disk.
        Storage::disk('public')->makeDirectory('exports');

        $absolutePath = Storage::disk('public')->path('exports/' . $this->fileName);
        try {
            IvrOpenSpoutExport::write($queryBuilder, $absolutePath, chunkSize: 2000, onChunk: fn() => abortIfCancelled($this->historyRef), userColumnsOrder: $this->columns);

            // Notify the user that the file is ready for download.
            MarkExportAsReady::dispatch($this->fileName, $user, ExportType::EXPORT_IVRS, $this->historyRef);
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
        \Log::error('OpenSpout IVR export failed', [
            'user_id'  => $this->userId,
            'fileName' => $this->fileName,
            'error'    => $exception?->getMessage(),
        ]);
    }
}
