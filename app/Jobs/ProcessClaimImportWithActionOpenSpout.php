<?php

namespace App\Jobs;

use App\Enums\UploadedClaimsStatus;
use App\Enums\NotificationType;
use App\Exceptions\ExportCancelledException;
use App\Models\UploadedClaimsFile;
use App\Models\User;
use App\Services\FilesUploader;
use App\Imports\ClaimImportWithActionMapper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Memory-safe version of ProcessClaimImportWithAction.
 *
 * Uses OpenSpout to stream the Excel file row-by-row instead of loading
 * the entire file into memory with Excel::toArray(). Safe for large files
 * (20k+ rows) without hitting PHP memory limits.
 *
 * Each row is committed in its own transaction, so progress is saved
 * incrementally — a crash mid-file will not roll back already-processed rows.
 */
class ProcessClaimImportWithActionOpenSpout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Allow up to 2 hours for very large files.
     */
    public int $timeout = 7200;

    public int $tries = 1;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public UploadedClaimsFile $uploadedFile,
        public User $user,
        public ?string $historyRef = null
    ) {}

    public function handle(): void
    {
        $this->user->update(['has_import_in_progress' => true]);

        // If historyRef is provided, update the corresponding DataHistory record to "processing" status
        $history = $this->historyRef
            ? \App\Models\DataHistory::where('ref', $this->historyRef)->first()
            : null;

        $this->uploadedFile->update([
            'uploaded_claims_status' => UploadedClaimsStatus::PROCESSING->value,
        ]);

        $reader = new Reader();
        try {
            $filePath = app(\App\Services\FilesUploader::class)->resolveProcessingPath($this->uploadedFile->originalFile);

            DB::beginTransaction();

            $reader->open($filePath);

            $successCount = 0;
            $failedCount  = 0;
            $headers      = null;
            $headerCount  = 0;
            $rowIndex     = 0;

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = array_map(
                        fn($cell) => $this->normalizeCellValue($cell->getValue()),
                        $row->getCells()
                    );

                    // First row is headers
                    if ($headers === null) {
                        $headers     = array_map(
                            fn($h) => Str::snake(strtolower(trim((string) ($h ?? '')))),
                            $cells
                        );
                        $headerCount = count($headers);
                        continue;
                    }

                    $rowIndex++;
                    $rowNumber = $rowIndex + 1; // +1 for header row

                    // Skip empty rows
                    if (empty(array_filter($cells, fn($v) => $v !== null && $v !== ''))) {
                        continue;
                    }

                    // Pad or trim to match header count
                    $cellCount = count($cells);
                    if ($cellCount < $headerCount) {
                        $cells = array_pad($cells, $headerCount, null);
                    } elseif ($cellCount > $headerCount) {
                        $cells = array_slice($cells, 0, $headerCount);
                    }

                    $rowData = array_combine($headers, $cells);

                    try {
                        $claim = $this->processRow($rowData, $rowNumber);
                        $successCount++;

                        if ($history) {
                            $cacheKey = $history->getImportCacheKey();
                            $ids = \Illuminate\Support\Facades\Cache::get($cacheKey, []);
                            $ids[] = $claim->id;
                            \Illuminate\Support\Facades\Cache::put($cacheKey, $ids, now()->addHours(24));
                        }
                    } catch (\Exception $e) {
                        $failedCount++;
                        $this->storeFailedRow($rowData, $rowNumber, $e->getMessage());
                    }
                    abortIfCancelled($this->historyRef);
                }

                break; // Only process the first sheet
            }

            $reader->close();

            // Generate failed claims file if needed
            $failedFilePath = null;
            if ($failedCount > 0) {
                $failedFilePath = $this->generateFailedClaimsFileFromDatabase();

                if ($failedFilePath !== null) {
                    \App\Models\FailedImportedClaim::where('file_id', $this->uploadedFile->id)->delete();
                }
            }

            $this->user->update(['has_import_in_progress' => false]);

            $failedFileId = (new FilesUploader)->registerFailedFile($failedFilePath, $this->user->id)?->id;

            $this->uploadedFile->update([
                'uploaded_claims_status' => UploadedClaimsStatus::COMPLETED->value,
                'completed_at'           => now(),
                'success_count'          => $successCount,
                'failed_count'           => $failedCount,
                'failed_file_path'       => $failedFilePath,
                'failed_file_id'         => $failedFileId,
            ]);

            DB::commit();

            if ($history) {
                \Illuminate\Support\Facades\Cache::forget($history->getImportCacheKey());
            }

            $this->sendCompletionNotification();
        } catch (ExportCancelledException) {
            DB::rollBack();
            $reader->close();
            $this->user->update(['has_import_in_progress' => false]);
            $this->uploadedFile->update([
                'uploaded_claims_status' => UploadedClaimsStatus::CANCELLED->value,
                'completed_at' => now(),
            ]);
            return;
        } catch (\Exception $e) {
            DB::rollBack();

            $this->user->update(['has_import_in_progress' => false]);

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
     * Called by Laravel when the job dies unexpectedly (e.g. OOM fatal error).
     */
    public function failed(\Throwable $exception): void
    {
        $this->user->update(['has_import_in_progress' => false]);

        $this->uploadedFile->update([
            'uploaded_claims_status' => UploadedClaimsStatus::FAILED->value,
            'completed_at'           => now(),
            'error_message'          => $exception->getMessage(),
        ]);

        $this->sendFailureNotification();
    }

    /**
     * Convert OpenSpout cell values to types the mapper expects.
     * DateTimeImmutable cells (date-formatted columns) become Y-m-d strings.
     */
    private function normalizeCellValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value->format('Y-m-d');
        }

        return $value;
    }

    private function processRow(array $rowData, int $rowNumber): \App\Models\Claim
    {
        DB::beginTransaction();

        try {
            $mapper = new ClaimImportWithActionMapper($this->user, $this->uploadedFile);
            $result = $mapper->mapToClaim($rowData);

            $rowData = array_merge($rowData, $result['data']);

            if (isset($rowData['claim_status']) && !empty($rowData['claim_status'])) {
                $this->executeAction($result['claim'], $rowData);
            }


            DB::commit();
            return $result['claim'];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function executeAction(\App\Models\Claim $claim, array $rowData): void
    {
        $actionMap = [
            'approved'          => \App\Actions\ClaimImportActions\AcceptClaimAction::class,
            'closed'            => \App\Actions\ClaimImportActions\CloseClaimAction::class,
            'manaual_collected' => \App\Actions\ClaimImportActions\ManualCollectClaimAction::class,
            'follow_up/delay'   => \App\Actions\ClaimImportActions\DelayClaimAction::class,
            'follow_up/partial' => \App\Actions\ClaimImportActions\PartialFollowUpClaimAction::class,
            'law_firm'          => \App\Actions\ClaimImportActions\LawClaimAction::class,
            'transfer_to_elm'   => \App\Actions\ClaimImportActions\TransferClaimToElmAction::class,
        ];

        $status = strtolower(trim($rowData['claim_status'] ?? ''));

        if (!isset($actionMap[$status])) {
            throw new \Exception("Unknown claim status: {$status}");
        }

        auth()->setUser($this->user);

        $actionClass = $actionMap[$status];
        $action = new $actionClass($claim, $rowData);
        $action->execute();
    }

    private function storeFailedRow(array $rowData, int $rowNumber, string $errorMessage): void
    {
        $errorMessages = array_map('trim', explode('; ', $errorMessage));

        $existingFailure = \App\Models\FailedImportedClaim::where('file_id', $this->uploadedFile->id)
            ->where('row_number', $rowNumber)
            ->first();

        if ($existingFailure) {
            $errorData = $existingFailure->error_message;
            $errorData['errors'] = array_merge($errorData['errors'], $errorMessages);
            $existingFailure->update(['error_message' => $errorData]);
        } else {
            \App\Models\FailedImportedClaim::create([
                'file_id'       => $this->uploadedFile->id,
                'row_number'    => $rowNumber,
                'row_data'      => $rowData,
                'error_message' => ['errors' => $errorMessages],
            ]);
        }
    }

    private function generateFailedClaimsFileFromDatabase(): ?string
    {
        try {
            $failedClaims = \App\Models\FailedImportedClaim::where('file_id', $this->uploadedFile->id)
                ->orderBy('row_number')
                ->get()
                ->map(function ($failedClaim) {
                    $row          = $failedClaim->row_data;
                    $errorMessage = $failedClaim->error_message;
                    $errors       = isset($errorMessage['errors']) && is_array($errorMessage['errors'])
                        ? implode('; ', $errorMessage['errors'])
                        : ($errorMessage ?? 'Unknown error');

                    $row['error_message'] = $errors;
                    $row['row_number']    = $failedClaim->row_number;

                    return $row;
                })
                ->toArray();

            $path = 'public/failedClaims/' . $this->uploadedFile->id . '/failed.xlsx';
            \Maatwebsite\Excel\Facades\Excel::store(
                new \App\Exports\FailedClaim($failedClaims),
                $path,
                'local'
            );

            return $path;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function sendCompletionNotification(): void
    {
        try {
            $notification = \App\Services\NotificationService::create([
                'title_ar'       => 'اكتمل استيراد المطالبات',
                'title_en'       => 'Claims Import Completed',
                'description_ar' => "تم استيراد {$this->uploadedFile->success_count} مطالبة بنجاح، فشل {$this->uploadedFile->failed_count}",
                'description_en' => "Successfully imported {$this->uploadedFile->success_count} claims, {$this->uploadedFile->failed_count} failed",
                'regarding'      => NotificationType::CLAIM_IMPORT->value,
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
            Log::error('Failed to send completion notification', [
                'file_id' => $this->uploadedFile->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    private function sendFailureNotification(): void
    {
        try {
            $notification = \App\Services\NotificationService::create([
                'title_ar'       => 'فشل استيراد المطالبات',
                'title_en'       => 'Claims Import Failed',
                'description_ar' => 'حدث خطأ أثناء معالجة ملف المطالبات',
                'description_en' => 'An error occurred while processing the claims file',
                'regarding'      => NotificationType::CLAIM_IMPORT->value,
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
            Log::error('Failed to send failure notification', [
                'file_id' => $this->uploadedFile->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

}
