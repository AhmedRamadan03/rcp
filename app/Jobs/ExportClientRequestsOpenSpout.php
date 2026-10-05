<?php

namespace App\Jobs;

use App\Enums\ExportType;
use App\Exceptions\ExportCancelledException;
use App\Exports\OpenSpout\ClientRequestOpenSpoutExport;
use App\Services\RejectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ExportClientRequestsOpenSpout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 2;

    public bool $deleteWhenMissingModels = true;

    protected array $filters;

    protected ?array $columns;

    protected int $userId;

    protected string $fileName;

    protected ?string $historyRef;

    public function __construct(
        array $filters,
        ?array $columns,
        object $user,
        string $fileName,
        ?string $historyRef = null
    ) {
        $this->filters = $filters;
        $this->columns = $columns;
        $this->userId = $user->id;
        $this->fileName = $fileName;
        $this->historyRef = $historyRef;

        $this->onQueue('exports');
    }

    public function handle(): void
    {
        $user = \App\Models\User::findOrFail($this->userId);
        
        $queryBuilder = (new RejectionService())->export($this->filters, $this->userId);

        Storage::disk('public')->makeDirectory('exports');

        $absolutePath = Storage::disk('public')->path('exports/' . $this->fileName);

        try {
            ClientRequestOpenSpoutExport::write(
                $queryBuilder,
                $absolutePath,
                $this->columns,
                chunkSize: 2000,
                onChunk: fn () => abortIfCancelled($this->historyRef)
            );

            MarkExportAsReady::dispatch($this->fileName, $user, ExportType::EXPORT_CLIENT_REQUESTS, $this->historyRef);
        } catch (ExportCancelledException) {
            Storage::disk('public')->delete('exports/' . $this->fileName);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        \Log::error('OpenSpout requests export failed', [
            'user_id' => $this->userId,
            'fileName' => $this->fileName,
            'error' => $exception?->getMessage(),
        ]);
    }
}
