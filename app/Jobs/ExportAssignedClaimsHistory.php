<?php

namespace App\Jobs;

use App\Enums\ExportType;
use App\Exceptions\ExportCancelledException;
use App\Exports\OpenSpout\AssigningOpenSpoutExport;
use App\Models\Assigning;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Queued job that exports the full assigning history (all reassignment
 * events, active and inactive) for claims matched by an Assigned Claims
 * Report filter set (see AssignedClaimsReportQuery::claimIds()).
 *
 * Deliberately bypasses any per-user role scoping — the claim set is
 * already fully resolved and authorized by the dashboard export endpoint,
 * same trust model as ExportStatusReportClaims / ExportStatusReportTransactions.
 */
class ExportAssignedClaimsHistory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 2;

    public bool $deleteWhenMissingModels = true;

    /** @var list<int> */
    protected array $claimIds;

    protected int $userId;

    protected string $fileName;
    protected array $details;

    protected array $summary;
    protected ?string $historyRef;

    /**
     * @param  list<int>  $claimIds  Claim IDs resolved by AssignedClaimsReportQuery::claimIds().
     */
    public function __construct(array $claimIds, User $user, string $fileName, array $details, array $summary, ?string $historyRef = null)
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

        $queryBuilder = Assigning::query()
            ->whereIn('claim_id', $this->claimIds)
            ->whereNull('deleted_at')
            ->orderBy('claim_id')
            ->orderBy('assign_date');

        Storage::disk('public')->makeDirectory('exports');

        $absolutePath = Storage::disk('public')->path('exports/'.$this->fileName);

        try {
            AssigningOpenSpoutExport::writeWithSummary(
                $queryBuilder,
                $absolutePath,
                $this->details,
                $this->summary,
                chunkSize: 2000,
                onChunk: fn() => abortIfCancelled($this->historyRef)
            );

            MarkExportAsReady::dispatch($this->fileName, $user, ExportType::EXPORT_ASSIGNED_CLAIMS, $this->historyRef);
        } catch (ExportCancelledException) {
            Storage::disk('public')->delete('exports/'.$this->fileName);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('Assigned claims history export failed', [
            'user_id' => $this->userId,
            'fileName' => $this->fileName,
            'claim_count' => count($this->claimIds),
            'error' => $exception?->getMessage(),
        ]);
    }
}