<?php

namespace App\Jobs;

use App\Enums\ExportType;
use App\Exceptions\ExportCancelledException;
use App\Exports\OpenSpout\ClaimTransactionOpenSpoutExport;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExportClaimTransactionsOpenSpout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 2;

    public bool $deleteWhenMissingModels = true;

    protected int $userId;

    protected int $claimId;

    protected ?array $filters;

    protected string $fileName;

    protected ?string $historyRef;

    protected array $columns;

    public function __construct(
        object $user,
        int $claimId,
        string $fileName,
        ?string $historyRef = null,
        array $columns = [],
        ?array $filters = []
    ) {
        $this->userId = $user->id;
        $this->claimId = $claimId;
        $this->fileName = $fileName;
        $this->historyRef = $historyRef;
        $this->columns = $columns;
        $this->filters = $filters;

        $this->onQueue('exports');
    }

    public function handle(): void
    {
        $user = User::findOrFail($this->userId);

        $queryBuilder = PaymentTransaction::filter($this->filters)
            ->whereHas('link', function ($query) {
                if (isset($this->filters['payment_link_id'])) {
                    $query->where('id', $this->filters['payment_link_id']);
                }

                $query->whereHas('payment', function ($query) {
                    $query->where('claim_id', $this->claimId);
                });
            })
            ->orderByDesc('created_at');

        Storage::disk('public')->makeDirectory('exports');

        $absolutePath = Storage::disk('public')->path('exports/' . $this->fileName);

        try {
            ClaimTransactionOpenSpoutExport::write(
                $queryBuilder,
                $absolutePath,
                chunkSize: 2000,
                onChunk: fn () => abortIfCancelled($this->historyRef),
                userColumnsOrder: $this->columns
            );

            MarkExportAsReady::dispatch(
                $this->fileName,
                $user,
                ExportType::EXPORT_PAYMENT_TRANSACTIONS,
                $this->historyRef
            );
        } catch (ExportCancelledException) {
            Storage::disk('public')->delete('exports/' . $this->fileName);

            Log::info('Claim transaction export cancelled by user', [
                'user_id' => $this->userId,
                'claim_id' => $this->claimId,
                'historyRef' => $this->historyRef,
            ]);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('OpenSpout claim transaction export failed', [
            'user_id' => $this->userId,
            'claim_id' => $this->claimId,
            'fileName' => $this->fileName,
            'historyRef' => $this->historyRef,
            'error' => $exception?->getMessage(),
        ]);
    }
}
