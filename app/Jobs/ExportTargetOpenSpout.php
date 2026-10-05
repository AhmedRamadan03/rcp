<?php

namespace App\Jobs;

use App\Enums\DataHistoryEnum;
use App\Enums\ExportType;
use App\Enums\Roles;
use App\Exceptions\ExportCancelledException;
use App\Exports\OpenSpout\ClaimOpenSpoutExport;
use App\Exports\OpenSpout\TargetOpenSpoutExport;
use App\Models\DataHistory;
use App\Services\ClaimService;
use App\Services\UserService;
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
class   ExportTargetOpenSpout implements ShouldQueue
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

    protected object $user;
    protected string $fileName;
    protected string $historyRef;
    protected array  $filters;



    /**
     * @param  array   $filters   The same filter array used by ClaimService.
     * @param  object  $user      Authenticated user (will be decomposed for safe serialisation).
     * @param  string  $fileName  Target file name inside storage/app/public/exports/.
     */
    public function __construct( object $user, string $fileName, $historyRef = null, array $filters = [])
    {
        $this->filters  = $filters;
        $this->user   = $user;
        $this->fileName = $fileName;
        $this->historyRef = $historyRef;
    
        // Dedicated queue so heavy exports don't block lighter jobs.
        $this->onQueue('exports');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Rebuild a minimal user object for ClaimService (avoids serialising the full model).

        $userService = new UserService();
        $queryBuilder = $userService
            ->getTargetCollectorsForSupervisor($this->user->id)
            ->with(['currentTarget', 'previousTarget'])
            ->where(function ($q) {
                $q->whereHas('currentTarget')
                    ->orWhereHas('previousTarget');
            });

        if (isset($this->filters['search'])) {
            $this->searchFilter($queryBuilder, $this->filters['search']);
        }

        if (isset($this->filters['status'])) {
            $this->statusFilter($queryBuilder, $this->filters['status']);
        }
       
        // Ensure the exports directory exists on the public disk. 
        Storage::disk('public')->makeDirectory('exports');

        $absolutePath = Storage::disk('public')->path('exports/' . $this->fileName);
        try {
            TargetOpenSpoutExport::write($queryBuilder, $absolutePath, chunkSize: 2000,  onChunk: fn() => abortIfCancelled($this->historyRef));


            // Notify the user that the file is ready for download.
            MarkExportAsReady::dispatch($this->fileName, $this->user, ExportType::EXPORT_TARGETS, $this->historyRef);
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
        \Log::error('OpenSpout target export failed', [
            'user_id'  => $this->user->id ?? null,
            'fileName' => $this->fileName,
            'error'    => $exception?->getMessage(),
        ]);
    }

     private function searchFilter($queryBuilder, $search)
    {
        $queryBuilder->where(function ($q) use ($search) {

            // البحث في اسم المستخدم
            $q->where('name', 'ILIKE', "%{$search}%")

                // البحث في currentTarget
                ->orWhereHas('currentTarget', function ($t) use ($search) {
                    $t->where('target', 'like', "%{$search}%")
                        ->orWhere('achieved', 'like', "%{$search}%")
                        ->orWhere('remaining', 'like', "%{$search}%");
                })

                // البحث في previousTarget
                ->orWhereHas('previousTarget', function ($t) use ($search) {
                    $t->where('target', 'like', "%{$search}%")
                        ->orWhere('achieved', 'like', "%{$search}%")
                        ->orWhere('remaining', 'like', "%{$search}%");
                });
        });
    }
    private function statusFilter($queryBuilder, $status)
    {
        $queryBuilder->where(function ($q) use ($status) {

            //1) Target Collected
            if ($status === 'collected') {
                $q->whereHas('currentTarget', function ($t) {
                    $t->whereColumn('achieved', '>=', 'target');
                });
            }

            //2) Target On Progress
            if ($status === 'progress') {
                $q->whereHas('currentTarget', function ($t) {
                    $t->whereColumn('achieved', '<', 'target');
                });
            }

            //3) Achieved Last Target
            if ($status === 'achieved_last') {
                $q->whereHas('previousTarget', function ($t) {
                    $t->whereColumn('achieved', '>=', 'target');
                });
            }

            //4) Missed Last Target
            if ($status === 'missed_last') {
                $q->whereHas('previousTarget', function ($t) {
                    $t->whereColumn('achieved', '<', 'target');
                });
            }
        });
    }
}
