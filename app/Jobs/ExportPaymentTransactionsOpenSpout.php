<?php

namespace App\Jobs;

use App\Enums\ExportType;
use App\Exceptions\ExportCancelledException;
use App\Exports\OpenSpout\PaymentTransactionOpenSpoutExport;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Queued job that exports claims to XLSX using OpenSpout.
 *
 * Designed for large datasets in constrained environments:
 *  - Database queue driver compatible (all properties are serialisable).
 *  - Memory-safe: OpenSpout streams to disk; DB rows are chunked.
 */
class ExportPaymentTransactionsOpenSpout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 2;

    public bool $deleteWhenMissingModels = true;

    protected array $filters;

    protected ?array $columns;

    protected User $user;

    protected string $fileName;

    protected ?string $historyRef;

    public function __construct(
        array $filters,
        ?array $columns,
        User $user,
        string $fileName,
        ?string $historyRef = null
    ) {
        $this->filters = $filters;
        $this->columns = $columns;
        $this->user = $user;
        $this->fileName = $fileName;
        $this->historyRef = $historyRef;

        $this->onQueue('exports');
    }

    public function handle(): void
    {
        $queryBuilder = (new TransactionService())->export($this->filters, $this->user->id);

        Storage::disk('public')->makeDirectory('exports');

        $absolutePath = Storage::disk('public')->path('exports/' . $this->fileName);

        try {
            PaymentTransactionOpenSpoutExport::write(
                $queryBuilder,
                $absolutePath,
                $this->columns,
                chunkSize: 2000,
                onChunk: fn () => abortIfCancelled($this->historyRef)
            );

            MarkExportAsReady::dispatch($this->fileName, $this->user, ExportType::EXPORT_PAYMENT_TRANSACTIONS, $this->historyRef);
        } catch (ExportCancelledException) {
            Storage::disk('public')->delete('exports/' . $this->fileName);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('OpenSpout transactions export failed', [
            'user_id' => $this->user->id,
            'fileName' => $this->fileName,
            'error' => $exception?->getMessage(),
        ]);
    }
}