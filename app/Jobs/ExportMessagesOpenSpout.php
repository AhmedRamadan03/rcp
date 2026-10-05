<?php

namespace App\Jobs;

use App\Enums\DataHistoryEnum;
use App\Enums\ExportType;
use App\Exceptions\ExportCancelledException;
use App\Exports\OpenSpout\MessageOpenSpoutExport;
use App\Models\DataHistory;
use App\Models\Message;
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
class ExportMessagesOpenSpout implements ShouldQueue
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
    public function __construct(object $user, string $fileName, $historyRef = null, array $columns = [], array $filters = [])
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

        $queryBuilder = $this->filterData(Message::query()->with('claim'));
        $queryBuilder->select([
                'id',
                'claim_id',
                'phone_number',
                'send_date',
                'status',
                'message_status',
                'status_message',
                'sent_count',
                'body',
                'is_elm_message',
                'national_id',
            ]);

        // Ensure the exports directory exists on the public disk.
        Storage::disk('public')->makeDirectory('exports');

        $absolutePath = Storage::disk('public')->path('exports/' . $this->fileName);

        try {
            MessageOpenSpoutExport::write(
                $queryBuilder,
                $absolutePath,
                chunkSize: 2000,
                onChunk: fn() => abortIfCancelled($this->historyRef),
                userColumnsOrder: $this->columns

            );


            MarkExportAsReady::dispatch(
                $this->fileName,
                $user,
                ExportType::EXPORT_MESSAGES,
                $this->historyRef
            );
            } catch (ExportCancelledException) {
                // المستخدم ألغى التصدير — نحذف الملف الناقص ونخرج بهدوء
                Storage::disk('public')->delete('exports/' . $this->fileName);
                Log::info('Message export cancelled by user', [
                    'user_id'    => $this->userId,
                    'historyRef' => $this->historyRef,
                ]);
            }
    }

    
   
    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        \Log::error('OpenSpout message export failed', [
            'user_id'  => $this->userId,
            'fileName' => $this->fileName,
            'historyRef' => $this->historyRef,
            'error'    => $exception?->getMessage(),
        ]);
    }


    private function filterData($queryBuilder)
    {
        if (isset($this->filters['claim_number'])) {
            $queryBuilder->whereHas('claim', function ($q) {
                $q->where('claim_number', 'ILIKE', '%' . $this->filters['claim_number'] . '%');
            });
        }
        if (isset($this->filters['debtor_phone'])) {
            $queryBuilder->whereHas('claim', function ($q) {
                $q->where('phone_number', 'ILIKE', '%' . $this->filters['debtor_phone'] . '%');
            });
        }
        if (isset($this->filters['national_id'])) {
            $queryBuilder->whereHas('claim', function ($q) {
                $q->where('debtor_nid', 'ILIKE', '%' . $this->filters['national_id'] . '%');
            });
        }
        if (isset($this->filters['message_type'])) {
            $queryBuilder->where('is_elm_message', $this->filters['message_type']);
        }
        if (isset($this->filters['message_body'])) {
            $queryBuilder->where('body', 'ILIKE', '%' . $this->filters['message_body'] . '%');
        }
        if (isset($this->filters['status'])) {
            $queryBuilder->whereIn('status', Arr::wrap($this->filters['status']));
        }
        if (isset($this->filters['message_status'])) {
             $text = strtolower($this->filters['message_status']);
            if ($text == 'sent') {
                $text = 'sended';
            }elseif($text == 'Failed'){
                $text = 'not sended';
            }
            $queryBuilder->where('status_message', 'ILIKE', '%' . $text . '%');
        }
        if (isset($this->filters['ids'])) {
            $queryBuilder->whereIn('id', Arr::wrap($this->filters['ids']));
        }
        if (isset($this->filters['date_from']) && isset($this->filters['date_to'])) {
            $queryBuilder->whereBetween('send_date', [$this->filters['date_from'],$this->filters['date_to']]);
        }
        if (isset($this->filters['created_at_from']) && isset($this->filters['created_at_to'])) {
            $queryBuilder->whereBetween('created_at', [$this->filters['created_at_from'],$this->filters['created_at_to']]);
        }

        if (isset($this->filters['search'])) {
            $queryBuilder->where(function ($q) {
                $q->where('phone_number', 'ILIKE', '%' . $this->filters['search'] . '%')
                    ->orWhere('body', 'ILIKE', '%' . $this->filters['search'] . '%')
                    ->orWhere('status', 'ILIKE', '%' . $this->filters['search'] . '%')
                    ->orWhere('status_message', 'ILIKE', '%' . $this->filters['search'] . '%')
                    ->orWhere('message_status', 'ILIKE', '%' . $this->filters['search'] . '%');
            });
        }
        return $queryBuilder;

    }
}
