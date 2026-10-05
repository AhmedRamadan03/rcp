<?php

namespace App\Jobs;

use App\Enums\DataHistoryEnum;
use App\Enums\NotificationType;
use App\Enums\UploadedClaimsStatus;
use App\Imports\BulkAssignClaimsMapper;
use App\Imports\UpdateClaimImport;
use App\Models\Assigning;
use App\Models\DataHistory;
use App\Models\FailedImportedClaim;
use App\Models\UploadedClaimsFile;
use App\Models\User;
use App\Services\FilesUploader;
use Dflydev\DotAccessData\Data;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;

class ProcessUpdateBilledClaimsOpenSpout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int  $timeout               = 7200;
    public int  $tries                 = 1;
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public UploadedClaimsFile $uploadedFile,
        public User $user,
        public string $invoiceStatus,
        public ?string $historyRef = null
    ) {}

    public function handle(): void
    {
        $this->uploadedFile->update([
            'uploaded_claims_status' => UploadedClaimsStatus::PROCESSING->value,
        ]);

        try {
            $filePath = app(\App\Services\FilesUploader::class)->resolveProcessingPath($this->uploadedFile->originalFile);

            $mapper      = new UpdateClaimImport();

            $successCount       = 0;
            $failedCount        = 0;

            $cacheKey = "update_billed:{$this->historyRef}:processed_ids";

            Cache::forget($cacheKey);

            $reader = new Reader();
            $reader->open($filePath);

            $headers     = null;
            $headerCount = 0;
            $rowIndex    = 0;

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $history = DataHistory::where('ref', $this->historyRef)->first();

                    if ($history && $history->status === DataHistoryEnum::CANCELLED->value) {

                        $reader->close();
                        return;
                    }
                    $cells = array_map(
                        fn($cell) => $cell->getValue(),
                        $row->getCells()
                    );

                    if ($headers === null) {
                        $headers     = array_map(
                            fn($h) => Str::snake(strtolower(trim((string) ($h ?? '')))),
                            $cells
                        );
                        $headerCount = count($headers);
                        continue;
                    }

                    $rowIndex++;
                    $rowNumber = $rowIndex + 1;

                    if (empty(array_filter($cells, fn($v) => $v !== null && $v !== ''))) {
                        continue;
                    }

                    $cellCount = count($cells);
                    if ($cellCount < $headerCount) {
                        $cells = array_pad($cells, $headerCount, null);
                    } elseif ($cellCount > $headerCount) {
                        $cells = array_slice($cells, 0, $headerCount);
                    }

                    $rowData = array_combine($headers, $cells);

                    try {
                        $result = $mapper->updateClaim($rowData, $this->invoiceStatus);

                        if ($result) {
                            $successCount++;

                            // Store the processed claim ID in Redis for quick access when generating the failed file
                            $ids = Cache::get($cacheKey, []);

                            $ids[] = $result->id;

                            Cache::put($cacheKey, $ids, now()->addHours(2));
                        }
                    } catch (\Exception $e) {
                        $failedCount++;
                        $this->storeFailedRow($rowData, $rowNumber, $e->getMessage());
                    }
                }

                break; // First sheet only
            }

            $reader->close();




            $failedFilePath = null;
            if ($failedCount > 0) {
                $failedFilePath = $this->generateFailedFile();
                if ($failedFilePath !== null) {
                    FailedImportedClaim::where('file_id', $this->uploadedFile->id)->delete();
                }
            }

            $failedFileId = (new FilesUploader)->registerFailedFile($failedFilePath, $this->user->id)?->id;

            $this->uploadedFile->update([
                'uploaded_claims_status' => UploadedClaimsStatus::COMPLETED->value,
                'completed_at'           => now(),
                'success_count'          => $successCount,
                'failed_count'           => $failedCount,
                'failed_file_path'       => $failedFilePath,
                'failed_file_id'         => $failedFileId,
            ]);



            $this->sendCompletionNotification();
        } catch (\Exception $e) {

            $this->uploadedFile->update([
                'uploaded_claims_status' => UploadedClaimsStatus::FAILED->value,
                'completed_at'           => now(),
                'error_message'          => $e->getMessage(),
            ]);

            $this->sendFailureNotification();

            throw $e;
        } finally {
            if (isset($filePath) && $this->uploadedFile->originalFile?->storage_driver === \App\Enums\StorageDriver::CLOUD) {
                @unlink($filePath);
            }
        }
    }

    /**
     * Called by Laravel when the job dies outside handle() —
     * OOM kills, worker timeouts, queue:restart, etc.
     * Ensures the file record never gets stuck on PROCESSING.
     */
    public function failed(\Throwable $exception): void
    {
        $this->uploadedFile->update([
            'uploaded_claims_status' => UploadedClaimsStatus::FAILED->value,
            'completed_at'           => now(),
            'error_message'          => $exception->getMessage(),
        ]);

        $this->sendFailureNotification();
    }

    private function storeFailedRow(array $rowData, int $rowNumber, string $errorMessage): void
    {
        $errorMessages = array_map('trim', explode('; ', $errorMessage));

        $existing = FailedImportedClaim::where('file_id', $this->uploadedFile->id)
            ->where('row_number', $rowNumber)
            ->first();

        if ($existing) {
            $data           = $existing->error_message;
            $data['errors'] = array_merge($data['errors'], $errorMessages);
            $existing->update(['error_message' => $data]);
        } else {
            FailedImportedClaim::create([
                'file_id'       => $this->uploadedFile->id,
                'row_number'    => $rowNumber,
                'row_data'      => $rowData,
                'error_message' => ['errors' => $errorMessages],
            ]);
        }
    }

    private function generateFailedFile(): ?string
    {
        try {
            $failedRows = FailedImportedClaim::where('file_id', $this->uploadedFile->id)
                ->orderBy('row_number')
                ->get()
                ->map(function ($fc) {
                    $row            = $fc->row_data;
                    $row['reasons'] = isset($fc->error_message['errors']) && is_array($fc->error_message['errors'])
                        ? $fc->error_message['errors']
                        : [$fc->error_message ?? 'Unknown error'];
                    return $row;
                })
                ->toArray();

            $path = 'public/failedBulkAssigns/' . $this->uploadedFile->id . '/failed.xlsx';

            \Maatwebsite\Excel\Facades\Excel::store(
                new \App\Exports\FailedUpdateClaimExport($failedRows),
                $path,
                'local'
            );

            return $path;
        } catch (\Exception $e) {
            Log::error('Failed to generate failed bulk assign file', [
                'file_id' => $this->uploadedFile->id,
                'error'   => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function sendCompletionNotification(): void
    {
        try {
            $notification = \App\Services\NotificationService::create([
                'title_ar'       => 'اكتمل تحديث فواتير المطالبات',
                'title_en' => 'Claims Billing Update Completed',
                'description_ar' => "تم تحديث {$this->uploadedFile->success_count} مطالبة بنجاح، فشل {$this->uploadedFile->failed_count}",
                'description_en' => "Successfully updated {$this->uploadedFile->success_count} claims, {$this->uploadedFile->failed_count} failed",
                'regarding'      => NotificationType::BILLED_UPDATE_CLAIM->value,
                'user_id'        => $this->user->id,
                'to'             => json_encode([$this->user->id]),
                'links'          => json_encode([[
                    'label_ar' => 'عرض النتائج',
                    'label_en' => 'View Results',
                    'url'      => "{$this->uploadedFile->id}",
                ]]),
            ]);
            if ($this->historyRef) {
                \App\Models\DataHistory::updateDataHistoryStatus($this->historyRef, $notification->id);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send bulk assign completion notification', [
                'file_id' => $this->uploadedFile->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    private function sendFailureNotification(): void
    {
        try {
            $notification = \App\Services\NotificationService::create([
                'title_ar'       => 'فشل تحديث فواتير المطالبات',
                'title_en'       => 'Claims Billing Update Failed',
                'description_ar' => 'حدث خطأ أثناء معالجة ملف التحديث',
                'description_en' => 'An error occurred while processing the update file',
                'regarding'      => NotificationType::BILLED_UPDATE_CLAIM->value,
                'user_id'        => $this->user->id,
                'to'             => json_encode([$this->user->id]),
                'links'          => json_encode([[
                    'label_ar' => 'عرض التفاصيل',
                    'label_en' => 'View Details',
                    'url'      => "{$this->uploadedFile->id}",
                ]]),
            ]);
            if ($this->historyRef) {
                \App\Models\DataHistory::updateDataHistoryStatus($this->historyRef, $notification->id);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send bulk assign failure notification', [
                'file_id' => $this->uploadedFile->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Register a failed-rows Excel file in the central files registry and
     * return the new files.id. Returns null when there is no failed file.
     */
}
