<?php

namespace App\Traits;

use App\Enums\ClaimFileAccess;
use App\Enums\CloudStorageDisk;
use App\Enums\FileSource;
use App\Models\Claim;
use Carbon\Carbon;

/**
 * Shared by ClaimRegistrationService and ClaimUpdateService for
 * 4 document handling.
 *
 * Deliberately NOT a new service — this calls the exact same two
 * EXISTING services the website already uses for uploads
 * (FilesUploader::upload() and ClaimService::uploadClaimFile()),
 * the same way ClaimInfoController::uploadFile() does. This trait
 * only holds the handful of lines needed to loop over documents and
 * call those two existing services with the right arguments.
 *
 * Does NOT fire UpdateClaimInstallmentEvent itself — callers differ on
 * when that should fire (ClaimUpdateService fires it unconditionally
 * once per item, matching the website's update(); ClaimRegistrationService
 * only fires it when documents were actually attached, matching
 * uploadFile()'s behavior) so firing it here would risk a double-fire.
 *
 * Requires the using class to already have these injected:
 *   protected/public FilesUploader $filesUploader
 *   protected/public ClaimService $claimService
 */
trait AttachesClaimDocuments
{
    /**
     * @param array<int, array{file: \Illuminate\Http\UploadedFile, title: ?string, access: ?string}> $documents
     * @return bool true if at least one document was actually uploaded
     */
    protected function attachDocuments(Claim $claim, array $documents, ?int $uploadedBy): bool
    {
        if (empty($documents)) {
            return false;
        }

        $attachedAny = false;

        foreach ($documents as $doc) {
            $file = $doc['file'] ?? null;

            if (!$file) {
                continue;
            }

            $uploaded = $this->filesUploader->upload($file, [
                'source' => FileSource::CLAIM_FILES,
                'directory' => 'claimFiles/' . Carbon::today()->year . '/' . $claim->id,
                'uploaded_by' => $uploadedBy,
                'disk' => CloudStorageDisk::GCS_PRIVATE,
            ]);

            $accessValue = $doc['access'] ?? null;

            $this->claimService->uploadClaimFile([
                'claim_id' => $claim->id,
                'title' => $doc['title'] ?? $uploaded->original_filename,
                'fileAccess' => $accessValue === ClaimFileAccess::PRIVATE->value
                    ? ClaimFileAccess::PRIVATE
                    : ClaimFileAccess::PUBLIC,
            ], $uploaded);

            $attachedAny = true;
        }

        return $attachedAny;
    }
}