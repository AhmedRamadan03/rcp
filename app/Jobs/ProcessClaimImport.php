<?php

namespace App\Jobs;

use App\Models\UploadedClaimsFile;
use App\Models\User;
use App\Services\FilesUploader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;


class ProcessClaimImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public UploadedClaimsFile $uploadedFile,
        public User $user
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Update user has_import_in_progress flag
        $this->user->update([
            'has_import_in_progress' => true,
        ]);


        // Update status to processing at start
        $this->uploadedFile->update([
            'uploaded_claims_status' => \App\Enums\UploadedClaimsStatus::PROCESSING->value,
        ]);

        try {
            // Get importer class
            $importClass = $this->getImportClass();
            $importer = new $importClass($this->user, $this->uploadedFile);

            // Import from stored file
            $filePath = app(\App\Services\FilesUploader::class)->resolveProcessingPath($this->uploadedFile->originalFile);
            \Maatwebsite\Excel\Facades\Excel::import($importer, $filePath);

            $successCount = \App\Models\Claim::where('file_id', $this->uploadedFile->id)->count();

            $failures = $importer->failures();
            $failedCount = $failures->count();

            // Store failed claims in database
            $failedFilePath = null;
            if ($failedCount > 0) {
                $this->storeFailedClaims($failures);
                $failedFilePath = $this->generateFailedClaimsFileFromDatabase();

                // delete failed_claims after generateFailedClaimsFile
                if ($failedFilePath !== null) {
                    \App\Models\FailedImportedClaim::where('file_id', $this->uploadedFile->id)->delete();
                }

            }

            $this->markCompleted($successCount, $failedCount, $failedFilePath);

        } catch (\Throwable $e) {
            $this->markFailed($e);
        } finally {
            $this->user->update(['has_import_in_progress' => false]);
            if (isset($filePath) && $this->uploadedFile->originalFile?->storage_driver === \App\Enums\StorageDriver::CLOUD) {
                @unlink($filePath);
            }
        }
    }

    /**
     * Handle a job failure (called by queue worker when the process is killed or times out).
     */
    public function failed(\Throwable $exception): void
    {
        $this->markFailed($exception);
        $this->user->update(['has_import_in_progress' => false]);
    }

    private function markCompleted(int $successCount, int $failedCount, ?string $failedFilePath): void
    {
        $failedFileId = (new FilesUploader)->registerFailedFile($failedFilePath, $this->user->id)?->id;

        $this->uploadedFile->update([
            'uploaded_claims_status' => \App\Enums\UploadedClaimsStatus::COMPLETED->value,
            'completed_at'           => now(),
            'success_count'          => $successCount,
            'failed_count'           => $failedCount,
            'failed_file_path'       => $failedFilePath,
            'failed_file_id'         => $failedFileId,
        ]);


            // Send completion notification
        $this->sendCompletionNotification();
    }

    private function markFailed(\Throwable $e): void
    {
        $this->uploadedFile->update([
            'uploaded_claims_status' => \App\Enums\UploadedClaimsStatus::FAILED->value,
            'completed_at' => now(),
            'error_message' => $e->getMessage(),
        ]);

        $this->sendFailureNotification();
    }

    /**
     * Get the appropriate import class based on client configuration.
     */
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

    /**
     * Store failed claims to database for programmatic access.
     *
     * @param \Illuminate\Support\Collection $failures Collection of failure objects from Laravel Excel
     * @return void
     */
    private function storeFailedClaims($failures): void
    {
        // Group failures by row number to combine multiple errors for the same row
        $groupedFailures = [];

        foreach ($failures as $failure) {
            // Handle both object and array formats
            if (is_object($failure)) {
                $rowNumber = $failure->row() ?? 2;
                $rowData = $failure->values() ?? [];
                $errors = $failure->errors() ?? [];
            } else {
                $rowNumber = $failure['row'] ?? 2;
                $rowData = $failure['values'] ?? [];
                $errors = $failure['errors'] ?? [];
            }

            // Initialize row if not exists
            if (!isset($groupedFailures[$rowNumber])) {
                $groupedFailures[$rowNumber] = [
                    'row_number' => $rowNumber,
                    'row_data' => $rowData,
                    'errors' => []
                ];
            }

            // Merge errors for this row
            $groupedFailures[$rowNumber]['errors'] = array_merge(
                $groupedFailures[$rowNumber]['errors'],
                $errors
            );
        }

        // Create database records for grouped failures
        foreach ($groupedFailures as $groupedFailure) {
            \App\Models\FailedImportedClaim::create([
                'file_id' => $this->uploadedFile->id,
                'row_number' => $groupedFailure['row_number'],
                'row_data' => json_encode($groupedFailure['row_data']),
                'error_message' => json_encode([
                    'errors' => $groupedFailure['errors']
                ]),
            ]);
        }
    }


    /**
     * Generate Excel file from failed_imported_claims database records.
     *
     * Reads failed claims from the database and generates an Excel file with all
     * original row columns plus error_message and row_number columns.
     *
     * @return string|null The file path where the failed claims file was stored, or null on error
     */
    private function generateFailedClaimsFileFromDatabase(): ?string
    {
        try {
            // Retrieve failed claims from database
            $failedClaims = \App\Models\FailedImportedClaim::where('file_id', $this->uploadedFile->id)
                ->orderBy('row_number')
                ->get()
                ->map(function ($failedClaim) {
                    // Decode row_data and add error information
                    $row = is_array($failedClaim->row_data)
                        ? $failedClaim->row_data
                        : json_decode($failedClaim->row_data, true);

                    $errorMessage = json_decode($failedClaim->error_message, true);

                    $errors = isset($errorMessage['errors']) && is_array($errorMessage['errors'])
                            ? implode('; ', $errorMessage['errors'])
                            : ($errorMessage ?? 'Unknown error');

                    $row['error_message'] = $errors;
                    $row['row_number'] = $failedClaim->row_number;

                    return $row;
                })
                ->toArray();

            // Store file in public/failedClaims/{file_id}/failed.xlsx (Requirement 3.3)
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

    /**
     * Send completion notification to the user.
     *
     * Sends a notification when the import completes successfully, including
     * success/failed counts and a link to view results.
     * Wrapped in try-catch to prevent notification failures from failing the job.
     *
     * @return void
     */
    private function sendCompletionNotification(): void
    {
        try {
            \App\Services\NotificationService::create([
                'title_ar' => 'اكتمل استيراد المطالبات',
                'title_en' => 'Claims Import Completed',
                'description_ar' => "تم استيراد {$this->uploadedFile->success_count} مطالبة بنجاح، فشل {$this->uploadedFile->failed_count}",
                'description_en' => "Successfully imported {$this->uploadedFile->success_count} claims, {$this->uploadedFile->failed_count} failed",
                'regarding' => \App\Enums\NotificationType::CLAIM_IMPORT->value,
                'user_id' => $this->user->id,
                'to' => json_encode([$this->user->id]),
                'links' => json_encode([
                    [
                        'label_ar' => 'عرض النتائج',
                        'label_en' => 'View Results',
                        'url' => "{$this->uploadedFile->id}",
                    ],
                ]),
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the job (Requirement 8.5)
            \Illuminate\Support\Facades\Log::error('Failed to send completion notification', [
                'file_id' => $this->uploadedFile->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send failure notification to the user.
     *
     * Sends a notification when the import fails with a fatal error,
     * including a link to view details.
     * Wrapped in try-catch to prevent notification failures from failing the job.
     *
     * @param string $errorMessage The error message from the exception
     * @return void
     */
    private function sendFailureNotification(): void
    {
        try {
            \App\Services\NotificationService::create([
                'title_ar' => 'فشل استيراد المطالبات',
                'title_en' => 'Claims Import Failed',
                'description_ar' => 'حدث خطأ أثناء معالجة ملف المطالبات',
                'description_en' => 'An error occurred while processing the claims file',
                'regarding' => \App\Enums\NotificationType::CLAIM_IMPORT->value,
                'user_id' => $this->user->id,
                'to' => json_encode([$this->user->id]),
                'links' => json_encode([
                    [
                        'label_ar' => 'عرض التفاصيل',
                        'label_en' => 'View Details',
                        'url' => "{$this->uploadedFile->id}",
                    ],
                ]),
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the job (Requirement 8.5)
            \Illuminate\Support\Facades\Log::error('Failed to send failure notification', [
                'file_id' => $this->uploadedFile->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
