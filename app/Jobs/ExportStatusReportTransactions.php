<?php

namespace App\Jobs;

use App\Enums\ExportType;
use App\Exceptions\ExportCancelledException;
use App\Exports\OpenSpout\PaymentTransactionOpenSpoutExport;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Queued job that exports payment transactions for the claims matched by
 * a Status Report filter set (see StatusReportQuery::claimIds()).
 *
 * Deliberately bypasses TransactionService's per-user role scoping.
 * The claim set is already fully resolved and authorized by the Status
 * Report export endpoint (entity/entity_ids scoping + the route's export
 * policy) — the same trust model ExportStatusReportClaims uses for the
 * Claims sheet. Re-applying role scoping here would make this export
 * narrower than the Claims export for identical filters.
 */
class ExportStatusReportTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 2;

    public bool $deleteWhenMissingModels = true;

    /** @var list<int> */
    protected array $claimIds;

    protected int $userId;

    protected ?array $details;
    protected ?array $summary;
    protected string $fileName;

    protected ?string $historyRef;

    /**
     * @param  list<int>  $claimIds  Claim IDs resolved by StatusReportQuery::claimIds().
     */
    public function __construct(array $claimIds, User $user, string $fileName, ?array $details, ?array $summary, ?string $historyRef = null)
    {
        $this->claimIds = $claimIds;
        $this->userId = $user->id;
        $this->fileName = $fileName;
        $this->details = $details;
        $this->summary = $summary;
        $this->historyRef = $historyRef;

        $this->onQueue('exports');
    }

    public function handle(): void
    {
        $user = User::findOrFail($this->userId);

        $queryBuilder = PaymentTransaction::query()
            ->join('payment_links', 'payment_links.id', '=', 'payment_transactions.link_id')
            ->join('payments', 'payments.id', '=', 'payment_links.payment_id')
            ->whereIn('payments.claim_id', $this->claimIds)
            ->select('payment_transactions.*')
            ->with('link.payment.claim')
            ->orderBy('payment_transactions.created_at', 'desc');

        Storage::disk('public')->makeDirectory('exports');

        $absolutePath = Storage::disk('public')->path('exports/'.$this->fileName);

        try {
            PaymentTransactionOpenSpoutExport::writeWithSummary(
                $queryBuilder,
                $absolutePath,
                null,
                $this->details,
                $this->summary,
                chunkSize: 2000,
                onChunk: fn() => abortIfCancelled($this->historyRef)
            );

            MarkExportAsReady::dispatch($this->fileName, $user, ExportType::EXPORT_PAYMENT_TRANSACTIONS, $this->historyRef);
        } catch (ExportCancelledException) {
            Storage::disk('public')->delete('exports/'.$this->fileName);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('Status report transactions export failed', [
            'user_id' => $this->userId,
            'fileName' => $this->fileName,
            'claim_count' => count($this->claimIds),
            'error' => $exception?->getMessage(),
        ]);
    }
}