<?php

namespace App\Services\Claim;

use App\BusinessLogic\PaymentReminder\PaymentReminder;
use App\Enums\AdditionalFileType;
use App\Enums\ClaimStatus;
use App\Enums\FileSource;
use App\Enums\FileVisibility;
use App\Models\AdditionalFile;
use App\Models\Claim;
use App\Models\CloseClaim;
use App\Models\CloseReason;
use App\Models\Setting;
use App\Models\User;
use App\Services\ClaimService;
use App\Services\FilesUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CloseClaimService
{
    public function __construct(
        private readonly FilesUploader $filesUploader,
        private readonly ClaimService $claimService,

    ) {}

    public function uploadCloseFile(Claim $claim,UploadedFile $file,int $uploadedBy,?string $note = null): AdditionalFile 
    {
        $fileTitle = ClaimStatus::getString(
            ClaimStatus::CLOSED->value
        );

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
            'type' => AdditionalFileType::CLAIM_CLOSED->value,
            'attachment_status' => FileVisibility::UNPUBLISHED->value,
        ]);
    }


    public function close(Claim $claim,array $data,?AdditionalFile $additionalFile = null,?ClaimActionOptions $options = null,?int $actingUserId = null): bool 
    {
        $options ??= ClaimActionOptions::systemApi();

        $actingUserId ??= auth()->id();
        $user = User::findOrFail($actingUserId);

        if ($claim->status == ClaimStatus::CLOSED->value) {

            throw new RuntimeException('This claim is already '.$claim->status);
        }

        return DB::transaction(function () use (
            $claim,
            $data,
            $additionalFile,
            $options,
            $actingUserId,
            $user
        ) {
            if ($options->requiresApproval) {
                
                if ($this->claimService->createRequestStatus(
                    $claim->id,
                    ClaimStatus::CLOSED->value,
                    $data,
                    $additionalFile,
                    'close_claim',
                    'claim_status',
                    $user
                )) {
                    return false;
                }
               
            }

            $this->performClose(
                $claim,
                $data,
                $additionalFile,
                $actingUserId
            );

            return true;
        });
    }

    private function performClose(Claim $claim,$request,?AdditionalFile $additionalFile = null,?int $actingUserId = null): void 
    {
        $previousStatus = $claim->status;

        $reasonId = $request['reason_id'] ?? null;

        if (!$reasonId && !empty($request['other_reason'])) {
            $reasonId = CloseReason::create([
                'reason' => $request['other_reason'],
            ])->id;
        }

        $closeReason = CloseReason::find($reasonId);

        CloseClaim::create([
            'claim_id' => $claim->id,
            'reason_id' => $reasonId,
            'url' => $additionalFile?->url ?? '',
        ]);

        $claim->update([
            'is_close' => 1,
            'status' => ClaimStatus::CLOSED->value,
        ]);

        $additionalFile?->update([
            'attachment_status' => FileVisibility::PUBLISHED->value,
        ]);

        PaymentReminder::handel(
            Setting::paymentReminderClosed(),
            $claim->id
        );

        $historyRequest = $request;

        $historyRequest['note'] = $closeReason?->reason ?? '';
        $historyRequest['file_id'] = $additionalFile?->file_id;

        $this->claimService->writeToClaimStatusHistory(
            $claim->id,
            $previousStatus,
            ClaimStatus::CLOSED->value,
            $actingUserId,
            $historyRequest
        );
    }

}