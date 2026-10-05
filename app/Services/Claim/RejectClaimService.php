<?php

namespace App\Services\Claim;

use App\Enums\AdditionalFileType;
use App\Enums\ClaimStatus;
use App\Enums\FileSource;
use App\Enums\FileVisibility;
use App\Models\AdditionalFile;
use App\Models\Claim;
use App\Models\CloseClaim;
use App\Models\CloseReason;
use App\Models\User;
use App\Services\ClaimService;
use App\Services\FilesUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Shared "reject / cancel" logic — used by BOTH
 * ClaimController::ClaimCancel() (website) and RejectedClaimStatusAction
 * (Partner API), the same way CloseClaimService is shared for close.
 *
 * IMPORTANT: unlike close(), the original ClaimService::cancel() has NO
 * approval gate — its createRequestStatus() call is commented out in
 * production, so cancel always executes immediately today, on the
 * website too. $options is still accepted here (for interface
 * consistency with the other actions, and in case an approval gate is
 * added to cancel later), but it currently has no effect.
 *
 * Also deliberately does NOT call PaymentReminder or NotificationService
 * — neither exists in the original ClaimService::cancel(). If you want
 * those for reject too, say so explicitly; they were not silently
 * carried over from CloseClaimService.
 *
 * Same latent-bug fix as CloseClaimService: the original cancel() has
 * `if ($request['reason_id'] == 0) { $request['close_id'] = ... }` —
 * a key nothing reads, so CloseClaim::create() still saves reason_id=0.
 * NOT reproduced here; the new reason's id is always keyed correctly.
 */
class RejectClaimService
{
    public function __construct(
        private readonly ClaimService $claimService,
        private readonly FilesUploader $filesUploader,
    ) {
    }

    public function uploadRejectFile(Claim $claim, UploadedFile $file, int $uploadedBy, ?string $note = null): AdditionalFile
    {
        $fileTitle = ClaimStatus::getString(ClaimStatus::REJECTED->value);
        $urlTitle = Str::slug($fileTitle, '_');

        $uploaded = $this->filesUploader->upload($file, [
            'source' => FileSource::ADDITIONAL_FILES,
            'directory' => 'close/' . now()->year . '/' . $claim->id . '/' . $urlTitle,
            'uploaded_by' => $uploadedBy,
        ]);

        $relativePath = $uploaded->local_path ?? $uploaded->cloud_path;

        return AdditionalFile::create([
            'title' => $fileTitle,
            'url' => $relativePath,
            'file_id' => $uploaded->id,
            'claim_id' => $claim->id,
            'note' => $note ?? '',
            'uploaded_by' => $uploadedBy,
            'type' => AdditionalFileType::CLAIM_REJECTED->value,
            'attachment_status' => FileVisibility::PUBLISHED->value,
        ]);
    }

    public function reject(
        Claim $claim,
        array $data,
        ?AdditionalFile $additionalFile,
        int $actingUserId,
        ?ClaimActionOptions $options = null
    ): void {
        $options ??= ClaimActionOptions::systemApi();

        // Resolved explicitly from $actingUserId (not auth()->user()) so
        // this works identically whether called from an authenticated
        // website session or a Partner API request with no session at all.
        $user = User::findOrFail($actingUserId);

        if ($claim->status !== ClaimStatus::REGISTERED->value) {
            throw new \RuntimeException('You can not perform this action');
        }
        if ($options->requiresApproval) {
            $perm = get_permission_value('claim_status', 'canceled', $user);
            if ($perm['granted'] == 0) {
                throw new \RuntimeException('ليس لديك صلاحية لهذا الإجراء');
            }
        }

        DB::transaction(function () use ($claim, $data, $additionalFile, $actingUserId) {
            $reasonId = $data['reason_id'] ?? null;

            if (!$reasonId && !empty($data['other_reason'])) {
                $reasonId = CloseReason::create(['reason' => $data['other_reason']])->id;
            }

            $closeReason = CloseReason::find($reasonId);

            CloseClaim::create([
                'claim_id' => $claim->id,
                'reason_id' => $reasonId,
                'url' => $additionalFile?->url ?? '',
            ]);

            $previousStatus = $claim->status;

            $claim->update([
                'is_close' => 1,
                'status' => ClaimStatus::REJECTED->value,
            ]);

            $historyData = $data;
            $historyData['note'] = $closeReason?->reason ?? '';
            $historyData['file_id'] = $additionalFile?->file_id;

            $this->claimService->writeToClaimStatusHistory(
                $claim->id,
                $previousStatus,
                ClaimStatus::REJECTED->value,
                $actingUserId,
                $historyData
            );
        });
    }
}