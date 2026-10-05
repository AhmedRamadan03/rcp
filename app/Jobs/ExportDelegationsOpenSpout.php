<?php

namespace App\Jobs;

use App\Enums\ExportType;
use App\Exceptions\ExportCancelledException;
use App\Exports\OpenSpout\DelegationOpenSpoutExport;
use App\Models\User;
use App\Services\DelegationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExportDelegationsOpenSpout implements ShouldQueue
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
        $queryBuilder = (new DelegationService())->export($this->filters, $this->user->id);

        Storage::disk('public')->makeDirectory('exports');

        $absolutePath = Storage::disk('public')->path('exports/' . $this->fileName);

        try {
            DelegationOpenSpoutExport::write(
                $queryBuilder,
                $absolutePath,
                $this->columns,
                chunkSize: 2000,
                onChunk: fn () => abortIfCancelled($this->historyRef)
            );

            MarkExportAsReady::dispatch($this->fileName, $this->user, ExportType::EXPORT_DELEGATIONS, $this->historyRef);
        } catch (ExportCancelledException) {
            Storage::disk('public')->delete('exports/' . $this->fileName);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('OpenSpout delegations export failed', [
            'user_id' => $this->user->id,
            'fileName' => $this->fileName,
            'error' => $exception?->getMessage(),
        ]);
    }
}