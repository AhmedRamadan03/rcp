<?php

namespace App\Jobs;

use App\Enums\ClaimStatus;
use App\Models\UploadedClaimsFile;
use App\Models\User;
use App\Services\FilesUploader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;


/**
 * Process claim import with status-based actions.
 *
 * This job processes an Excel file containing claims data and executes
 * status-based actions (e.g., approve, reject) for each claim within
 * the same transaction. Each row is processed individually to ensure
 * data integrity and proper failure handling.
 *
 * @package App\Jobs
 */
class ProcessClaimImportWithAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param UploadedClaimsFile $uploadedFile The uploaded claims file to process
     * @param User $user The user who initiated the import
     */
    public function __construct(
        public UploadedClaimsFile $uploadedFile,
        public User $user
    ) {
    }

    /**
     * Execute the job.
     *
     * Main execution flow that orchestrates the entire import process:
     * 1. Sets user's import flag and file status to PROCESSING
     * 2. Loads and parses the Excel file
     * 3. Processes each row individually within its own transaction
     * 4. Generates failed claims file if there are failures
     * 5. Updates file status with success/failed counts
     * 6. Sends completion notification
     *
     * If a fatal error occurs, the job:
     * - Rolls back all changes
     * - Sets file status to FAILED
     * - Resets user's import flag
     * - Sends failure notification
     * - Re-throws the exception for queue retry
     *
     * @return void
     * @throws \Exception If a fatal error occurs during processing
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
            \Illuminate\Support\Facades\DB::beginTransaction();

            // Load Excel file and get rows
            $filePath = app(\App\Services\FilesUploader::class)->resolveProcessingPath($this->uploadedFile->originalFile);
            $rows = \Maatwebsite\Excel\Facades\Excel::toArray(new \stdClass(), $filePath)[0];

            // Extract headers and normalize to snake_case
            $headers = array_shift($rows);
            $headers = array_map(function ($header) {
                return \Illuminate\Support\Str::snake(strtolower(trim($header ?? '')));
            }, $headers);

            $successCount = 0;
            $failedCount = 0;
            $headerCount = count($headers);

            // Process each row individually
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 for header row and 0-based index

                // Skip null or non-array rows
                if (!is_array($row)) {
                    continue;
                }

                // Skip empty rows
                if (empty(array_filter($row, fn($value) => $value !== null && $value !== ''))) {
                    continue;
                }

                // Pad or trim the row to match the header count so array_combine won't fail
                $rowCount = count($row);
                if ($rowCount < $headerCount) {
                    $row = array_pad($row, $headerCount, null);
                } elseif ($rowCount > $headerCount) {
                    $row = array_slice($row, 0, $headerCount);
                }

                // Combine headers with row data
                $rowData = array_combine($headers, $row);

                try {
                    $this->processRow($rowData, $rowNumber);
                    $successCount++;
                } catch (\Exception $e) {
                    $failedCount++;
                    // Store failure but continue processing
                    $this->storeFailedRow($rowData, $rowNumber, $e->getMessage());
                }
            }

            // Generate failed claims file if there are failures
            $failedFilePath = null;
            if ($failedCount > 0) {
                $failedFilePath = $this->generateFailedClaimsFileFromDatabase();

                // Delete failed_claims after generateFailedClaimsFile
                if ($failedFilePath !== null) {
                    \App\Models\FailedImportedClaim::where('file_id', $this->uploadedFile->id)->delete();
                }
            }

            // Update user import_flag
            $this->user->update([
                'has_import_in_progress' => false,
            ]);

            // Update status to completed
            $failedFileId = (new FilesUploader)->registerFailedFile($failedFilePath, $this->user->id)?->id;

            $this->uploadedFile->update([
                'uploaded_claims_status' => \App\Enums\UploadedClaimsStatus::COMPLETED->value,
                'completed_at'           => now(),
                'success_count'          => $successCount,
                'failed_count'           => $failedCount,
                'failed_file_path'       => $failedFilePath,
                'failed_file_id'         => $failedFileId,
            ]);

            \Illuminate\Support\Facades\DB::commit();

            // Send completion notification
            $this->sendCompletionNotification();

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();

            $this->user->update([
                'has_import_in_progress' => false,
            ]);

            $this->uploadedFile->update([
                'uploaded_claims_status' => \App\Enums\UploadedClaimsStatus::FAILED->value,
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
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
     * Process a single row with transaction management.
     *
     * Creates a claim and executes the corresponding action within a single transaction.
     * If either operation fails, the entire transaction is rolled back to maintain
     * data integrity. This ensures atomicity - either both the claim creation and
     * action execution succeed, or neither persists.
     *
     * @param array $rowData The row data from Excel (headers combined with values)
     * @param int $rowNumber The row number in the Excel file (including header offset)
     * @return void
     * @throws \Exception If claim creation or action execution fails
     */
    private function processRow(array $rowData, int $rowNumber): void
    {
        \Illuminate\Support\Facades\DB::beginTransaction();

        try {
            // Use mapper to create claim
            $mapper = new \App\Imports\ClaimImportWithActionMapper($this->user, $this->uploadedFile);
            $result = $mapper->mapToClaim($rowData);
            
            // Merge result data into rowData
            $rowData = array_merge($rowData, $result['data']);
            // Execute action if claim_status is provided
            if (isset($rowData['claim_status']) && !empty($rowData['claim_status'])) {
                $this->executeAction($result['claim'], $rowData);
            }

            \Illuminate\Support\Facades\DB::commit();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            throw $e;
        }
    }

    /**
     * Execute action based on claim status.
     *
     * Routes the claim to the appropriate action class based on the status value.
     * Currently supports:
     * - "approved" -> AcceptClaimAction (creates payment, updates status)
     *
     * Future statuses can be added to the action map.
     * Sets the authentication context to the job's user before executing the action,
     * as actions may rely on auth()->user() for authorization and audit trails.
     *
     * @param \App\Models\Claim $claim The claim to execute action on
     * @param string $status The claim status from Excel (e.g., "approved", "rejected")
     * @return void
     * @throws \Exception If action execution fails or status is unknown
     */
    private function executeAction(\App\Models\Claim $claim, array $rowData): void
    {
        // Map claim_status to action classes
        $actionMap = [
            'approved' => \App\Actions\ClaimImportActions\AcceptClaimAction::class,
            'closed' => \App\Actions\ClaimImportActions\CloseClaimAction::class,
            'manaual_collected' => \App\Actions\ClaimImportActions\ManualCollectClaimAction::class,
            'follow_up/delay' => \App\Actions\ClaimImportActions\DelayClaimAction::class,
            'follow_up/partial' => \App\Actions\ClaimImportActions\PartialFollowUpClaimAction::class,
            'law_firm' => \App\Actions\ClaimImportActions\LawClaimAction::class,
        ];

        $status = strtolower(trim($rowData['claim_status'] ?? ''));

        if (!isset($actionMap[$status])) {
            throw new \Exception("Unknown claim status: {$status}");
        }

        // Set authentication context for queued jobs
        // Actions like AcceptClaimAction use auth()->user() which requires authentication context
        auth()->setUser($this->user);

        $actionClass = $actionMap[$status];
        $action = new $actionClass($claim, $rowData);
        $action->execute();
    }

    /**
     * Store a failed row in the database.
     *
     * @param array $rowData The row data that failed
     * @param int $rowNumber The row number in the Excel file
     * @param string $errorMessage The error message
     * @return void
     */
    /**
     * Store failed row in database, grouping multiple errors for the same row.
     *
     * If a failure record already exists for this row number, the new error
     * is appended to the existing errors array. Otherwise, a new record is created.
     * This allows multiple validation or processing errors to be captured for
     * a single row without creating duplicate records.
     *
     * The error_message field uses JSON format with an 'errors' array to support
     * multiple error messages per row.
     *
     * @param array $rowData The complete row data from Excel (headers as keys)
     * @param int $rowNumber The row number in Excel (including header offset, typically index + 2)
     * @param string $errorMessage The error message to store (exception message or validation error)
     * @return void
     */
    private function storeFailedRow(array $rowData, int $rowNumber, string $errorMessage): void
    {
        // Split error message by semicolon to handle multiple validation errors
        $errorMessages = array_map('trim', explode('; ', $errorMessage));

        // Check if a failure record already exists for this row
        $existingFailure = \App\Models\FailedImportedClaim::where('file_id', $this->uploadedFile->id)
            ->where('row_number', $rowNumber)
            ->first();

        if ($existingFailure) {
            // Append errors to existing record (error_message is already decoded by model cast)
            $errorData = $existingFailure->error_message;
            $errorData['errors'] = array_merge($errorData['errors'], $errorMessages);

            $existingFailure->update([
                'error_message' => $errorData,
            ]);
        } else {
            // Create new failure record (model cast will handle encoding)
            \App\Models\FailedImportedClaim::create([
                'file_id' => $this->uploadedFile->id,
                'row_number' => $rowNumber,
                'row_data' => $rowData,
                'error_message' => [
                    'errors' => $errorMessages
                ],
            ]);
        }
    }

    /**
     * Generate Excel file from failed_imported_claims database records.
     *
     * Reads failed claims from the database and generates an Excel file with all
     * original row columns plus error_message and row_number columns. The file
     * is stored at public/failedClaims/{file_id}/failed.xlsx.
     *
     * After successful generation, the temporary database records are cleaned up
     * as the Excel file becomes the permanent record of failures.
     *
     * Error messages from multiple validation failures are concatenated with
     * semicolon separators for readability in the Excel file.
     *
     * @return string|null The file path where the failed claims file was stored, or null if generation fails
     */
    private function generateFailedClaimsFileFromDatabase(): ?string
    {
        try {
            // Retrieve failed claims from database
            $failedClaims = \App\Models\FailedImportedClaim::where('file_id', $this->uploadedFile->id)
                ->orderBy('row_number')
                ->get()
                ->map(function ($failedClaim) {
                    // Get row_data (already decoded by model cast)
                    $row = $failedClaim->row_data;

                    // Get error_message (already decoded by model cast)
                    $errorMessage = $failedClaim->error_message;

                    // Extract errors array and join into string
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
     * Sends a bilingual (Arabic/English) notification when the import completes
     * successfully, including success/failed counts and a link to view results.
     *
     * Wrapped in try-catch to prevent notification failures from failing the job.
     * If notification sending fails, the error is logged but the job continues
     * successfully. This ensures that notification service issues don't affect
     * the core import functionality.
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
     * Sends a bilingual (Arabic/English) notification when the import fails with
     * a fatal error, including a link to view details.
     *
     * Wrapped in try-catch to prevent notification failures from failing the job.
     * If notification sending fails, the error is logged but the exception is not
     * re-thrown. This ensures that notification service issues don't mask the
     * original import failure.
     *
     * @param string $errorMessage The error message from the exception that caused the job to fail
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

    /**
     * Handle a job failure (including fatal errors like OOM that bypass the catch block).
     */
    public function failed(\Throwable $exception): void
    {
        $this->user->update(['has_import_in_progress' => false]);

        $this->uploadedFile->update([
            'uploaded_claims_status' => \App\Enums\UploadedClaimsStatus::FAILED->value,
            'completed_at' => now(),
            'error_message' => $exception->getMessage(),
        ]);

        $this->sendFailureNotification();
    }

    /**
     * Register a failed-rows Excel file in the central files registry and
     * return the new files.id. Returns null when there is no failed file.
     */
}
