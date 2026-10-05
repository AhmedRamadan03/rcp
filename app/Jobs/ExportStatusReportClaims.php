<?php

namespace App\Jobs;

use App\Enums\ExportType;
use App\Exceptions\ExportCancelledException;
use App\Exports\OpenSpout\ClaimDashboardExport;
use App\Models\Claim;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Queued job that exports the claims matched by a Status Report filter set
 * to XLSX using OpenSpout — one "Claims" sheet plus one "Summary" sheet.
 *
 * Unlike ExportClaimsOpenSpout, the claim set here is already fully resolved
 * (StatusReportQuery::claimIds()) before dispatch, so the job just re-hydrates
 * by ID rather than re-running ClaimService's filter/role logic.
 *
 * Designed for large datasets in constrained environments:
 *  - Database queue driver compatible (all properties are serialisable).
 *  - Memory-safe: OpenSpout streams to disk; DB rows are chunked.
 */
class ExportStatusReportClaims implements ShouldQueue
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

    /** @var list<int> */
    protected array $claimIds;

    protected int $userId;

    protected string $fileName;

    protected array $details;
    /**
     * Full-set totals from StatusReportQuery::aggregateTotals(), written to
     * the Summary sheet. Shape: cleansed_count, cleansed_amount,
     * collected_count, collected_amount, outstanding_count, outstanding_amount.
     */
    protected array $summary;

    protected string $historyRef;

    /**
     * @param  list<int>  $claimIds   Claim IDs resolved by StatusReportQuery::claimIds().
     * @param  object     $user       Authenticated user (decomposed for safe serialisation).
     * @param  string     $fileName   Target file name inside storage/app/public/exports/.
     * @param  array      $details    Claim details for the Claims sheet.
     * @param  array      $summary    Totals for the Summary sheet.
     * @param  string     $historyRef DataHistory ref, used for cancellation checks + notification.
     */
    public function __construct(array $claimIds, object $user, string $fileName, array $details, array $summary, string $historyRef)
    {
        $this->claimIds = $claimIds;
        $this->userId = $user->id;
        $this->fileName = $fileName;
        $this->details  = $details;
        $this->summary = $summary;
        $this->historyRef = $historyRef;

        // Dedicated queue so heavy exports don't block lighter jobs.
        $this->onQueue('exports');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = User::findOrFail($this->userId);

        // Claim set is already fully resolved — no ClaimService/role branching needed.
        $queryBuilder = Claim::query()->whereIn('id', $this->claimIds);

        Storage::disk('public')->makeDirectory('exports');

        $absolutePath = Storage::disk('public')->path('exports/'.$this->fileName);

        try {
            ClaimDashboardExport::writeWithSummary(
                $queryBuilder,
                $absolutePath,
                $this->details,
                $this->summary,
                chunkSize: 2000,
                onChunk: fn () => abortIfCancelled($this->historyRef)
            );

            // Notify the user that the file is ready for download.
            MarkExportAsReady::dispatch($this->fileName, $user, ExportType::EXPORT_CLAIMS, $this->historyRef);
        } catch (ExportCancelledException) {
            Storage::disk('public')->delete('exports/'.$this->fileName);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        \Log::error('Status report claims export failed', [
            'user_id' => $this->userId,
            'fileName' => $this->fileName,
            'claim_count' => count($this->claimIds),
            'error' => $exception?->getMessage(),
        ]);
    }
}