<?php

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Enums\UploadedClaimsStatus;
use App\Exceptions\ExportCancelledException;
use App\Models\DataHistory;
use App\Models\FailedImportedClaim;
use App\Models\UploadedClaimsFile;
use App\Models\User;
use App\Services\FilesUploader;
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
 * Memory-safe replacement for ProcessClaimImport.
 *
 * Streams the Excel file row-by-row via OpenSpout instead of loading the
 * entire workbook into memory with Maatwebsite Excel::toArray().
 *
 * The per-client import class (ArtClaimImport, GIG\ClaimImport, etc.) is
 * resolved exactly like ProcessClaimImport did via getImportClass(), but
 * instead of handing the whole file to Maatwebsite, we call model() on it
 * directly for each row — the same pattern ProcessClaimImportWithActionOpenSpout
 * uses with ClaimImportWithActionMapper::mapToClaim().
 */
class ProcessClaimImportOpenSpout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Allow up to 2 hours for very large files. */
    public int $timeout = 7200;

    public int $tries = 1;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public UploadedClaimsFile $uploadedFile,
        public User $user,
        public  $historyRef = null,
    ) {}

    public function handle(): void
    {
        $this->user->update(['has_import_in_progress' => true]);

        $history = $this->historyRef
            ? \App\Models\DataHistory::where('ref', $this->historyRef)->first()
            : null;

        $this->uploadedFile->update([
            'uploaded_claims_status' => UploadedClaimsStatus::PROCESSING->value,
        ]);

        $reader = new Reader();
        try {
            $filePath = app(\App\Services\FilesUploader::class)->resolveProcessingPath($this->uploadedFile->originalFile);

            // Resolve the same per-client importer class that ProcessClaimImport used,
            // and instantiate it — its model() method will be called per row below.
            $importClass = $this->getImportClass();
            $importer    = new $importClass($this->user, $this->uploadedFile);

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
                    $rowNumber = $rowIndex + 1; // +1 to account for the header row

                    // Skip blank rows
                    if (empty(array_filter($cells, fn($v) => $v !== null && $v !== ''))) {
                        continue;
                    }

                    // Align cell count with header count
                    $cellCount = count($cells);
                    if ($cellCount < $headerCount) {
                        $cells = array_pad($cells, $headerCount, null);
                    } elseif ($cellCount > $headerCount) {
                        $cells = array_slice($cells, 0, $headerCount);
                    }

                    $rowData = array_combine($headers, $cells);

                    try {
                        $claim = $this->processRow($importer, $rowData, $rowNumber);
                        $successCount++;


                        if ($history) {
                            $cacheKey = $history->getImportNewClaimCacheKey();
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

            // Run post-import logic (assignments, notifications) — replaces afterImport event
            if (method_exists($importer, 'runPostImportAssignments')) {
                $importer->runPostImportAssignments();
            }

            // Generate failed-claims Excel if needed
            $failedFilePath = null;
            if ($failedCount > 0) {
                $failedFilePath = $this->generateFailedClaimsFileFromDatabase();

                if ($failedFilePath !== null) {
                    FailedImportedClaim::where('file_id', $this->uploadedFile->id)->delete();
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

            if ($history) {
                \Illuminate\Support\Facades\Cache::forget($history->getImportNewClaimCacheKey());
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
     * Called by Laravel when the job dies unexpectedly (OOM, timeout, etc.).
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

    // -------------------------------------------------------------------------
    // Row processing
    // -------------------------------------------------------------------------

    /**
     * Validate and persist one row by delegating to the client importer's
     * prepareForValidation() + rules() + model() methods — exactly how
     * ProcessClaimImportWithActionOpenSpout delegates to
     * ClaimImportWithActionMapper::mapToClaim().
     */
    private function processRow(object $importer, array $rowData, int $rowNumber)
    {
        DB::beginTransaction();

        try {
            // Mirror the Maatwebsite lifecycle: prepare → validate → model
            if (method_exists($importer, 'prepareForValidation')) {
                $rowData = $importer->prepareForValidation($rowData, $rowNumber);
            }

            if (method_exists($importer, 'rules')) {
                $rules    = $importer->rules();
                $messages = method_exists($importer, 'customValidationMessages')
                    ? $importer->customValidationMessages()
                    : [];

                $validator = \Illuminate\Support\Facades\Validator::make($rowData, $rules, $messages);

                if ($validator->fails()) {
                    throw new \Exception(implode('; ', $validator->errors()->all()));
                }
            }

            $result = $importer->model($rowData);

            DB::commit();
            return $result;    
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Client import class resolution (mirrors ProcessClaimImport::getImportClass)
    // -------------------------------------------------------------------------

    private function getImportClass(): string
    {
        $clientName = config('app.client_name');

        $importMap = [
            'GIG' => \App\Imports\GIG\ClaimImport::class,
            'GGI' => \App\Imports\UploadClaimsImport::class,
            'MIC' => \App\Imports\GIG\ClaimImport::class,
            'ART' => \App\Imports\ART\ArtClaimImport::class,
        ];

        return $importMap[$clientName] ?? \App\Imports\GIG\ClaimImport::class;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Convert OpenSpout cell values to types the importer expects.
     * DateTimeImmutable cells (date-formatted columns) become Y-m-d strings.
     */
    private function normalizeCellValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value->format('Y-m-d');
        }

        return $value;
    }

    private function storeFailedRow(array $rowData, int $rowNumber, string $errorMessage): void
    {
        $errorMessages = array_map('trim', explode('; ', $errorMessage));

        $existing = FailedImportedClaim::where('file_id', $this->uploadedFile->id)
            ->where('row_number', $rowNumber)
            ->first();

        if ($existing) {
            $errorData           = $existing->error_message;
            $errorData['errors'] = array_merge($errorData['errors'], $errorMessages);
            $existing->update(['error_message' => $errorData]);
        } else {
            FailedImportedClaim::create([
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
            $failedClaims = FailedImportedClaim::where('file_id', $this->uploadedFile->id)
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
            Log::error('Failed to generate failed claims file', [
                'file_id' => $this->uploadedFile->id,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }
    }

    // -------------------------------------------------------------------------
    // Notifications
    // -------------------------------------------------------------------------

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
                DataHistory::updateDataHistoryStatus($this->historyRef, $notification->id);
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
                DataHistory::updateDataHistoryStatus($this->historyRef, $notification->id);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send failure notification', [
                'file_id' => $this->uploadedFile->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

}
