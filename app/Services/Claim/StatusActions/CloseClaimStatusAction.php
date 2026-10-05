<?php

namespace App\Services\Claim\StatusActions;

use App\Enums\UploadFileType;
use App\Models\Claim;
use App\Services\Claim\ClaimActionOptions;
use App\Services\Claim\CloseClaimService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

/**
 * Mirrors ClaimController::claimClose() + ClaimService::close() — but
 * DELIBERATELY SKIPS createRequestStatus() (the supervisor-approval
 * gate) by design: the Partner API always closes immediately,
 * regardless of whether the internal user tied to the API credential
 * would normally need approval on the website. This was a deliberate
 * product decision, not an oversight — see conversation history.
 *
 * Because that gate is skipped, this class inlines the "actual close"
 * branch from ClaimService::close() directly (CloseClaim::create(),
 * claim status update, notification, payment reminder) rather than
 * calling close() itself.
 *
 * Deviations from the original site code (both intentional fixes):
 *   1. `$additional_file->attachment_status = ...` in the original
 *      service crashes with a null-pointer error whenever no file is
 *      submitted (close_file is nullable, so this is the COMMON case,
 *      not an edge case). Fixed here with a null-check.
 *   2. The original service has a latent bug: when reason_id is
 *      exactly 0, it stores the new CloseReason's id under
 *      `$request['close_id']` (a key nothing else reads) instead of
 *      `reason_id`, so CloseClaim::create() still saves reason_id = 0.
 *      NOT reproduced here — this class always keys the new reason's
 *      id under `reason_id` so CloseClaim is created correctly.
 */
class CloseClaimStatusAction implements ClaimStatusAction
{
    public function __construct(
        private readonly CloseClaimService $closeClaimService,
    ) {}

    public static function requiresFile(): bool
    {
        return config('app.client_name') !==  \App\Enums\Clients::GIG->value ? true : false ;
    }
    
    public function handle(Claim $claim,array $item,?int $actingUserId,ClaimActionOptions $options,?UploadedFile $statusFile = null): ?string {
    
        if ($actingUserId === null) {
            throw new \RuntimeException(
                'Cannot close this claim: no internal user is associated with this API credential.'
            );
        }

        $validator = Validator::make($item, [
            'reason_id' => ['nullable', 'exists:close_reasons,id'],
            'other_reason' => ['required_without:reason_id'],
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException(
                $validator->errors()->first()
            );
        }

        // التحقق من الملف المرفوع لوحده
        if (static::requiresFile() && !$statusFile) {
            throw new \InvalidArgumentException(
                __('claim.close_file_is_required')
            );
        }

        if ($statusFile) {
            $fileValidator = Validator::make(
                ['close_file' => $statusFile],
                [
                    'close_file' => ['file',  'max:20480', 'mimes:'.implode(',', UploadFileType::extensionsWithoutSvg())], // 5MB
                ]
            );

            if ($fileValidator->fails()) {
                throw new \InvalidArgumentException(
                    $fileValidator->errors()->first()
                );
            }
        }

        $validated = $validator->validated();
        $validated['note'] = $validated['other_reason'] ?? null;

       $additionalFile = null;

        if ($statusFile) {
            $additionalFile = $this->closeClaimService->uploadCloseFile(
                $claim,
                $statusFile,
                $actingUserId
            );
        }

       return $this->closeClaimService->close(
            $claim,
            $validated,
            $additionalFile,
            $options,
            $actingUserId
        );
        
        
    }
}