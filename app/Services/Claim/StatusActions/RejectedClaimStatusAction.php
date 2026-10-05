<?php

namespace App\Services\Claim\StatusActions;

use App\Enums\UploadFileType;
use App\Models\Claim;
use App\Services\Claim\ClaimActionOptions;
use App\Services\Claim\RejectClaimService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

/**
 * Thin adapter: validates the Partner-API-specific input shape, then
 * delegates to RejectClaimService — the SAME service the website's
 * ClaimController::ClaimCancel() uses.
 */
class RejectedClaimStatusAction implements ClaimStatusAction
{
    public function __construct(
        private readonly RejectClaimService $rejectClaimService,
    ) {
    }

    public static function requiresFile(): bool
    {
        return config('app.client_name') !== \App\Enums\Clients::GIG->value;
    }

    public function handle(Claim $claim, array $item, ?int $actingUserId, ClaimActionOptions $options, ?UploadedFile $statusFile = null): ?string
    {
        if ($actingUserId === null) {
            throw new \RuntimeException(
                'Cannot reject this claim: no internal user is associated with this API credential.'
            );
        }

        // Same validation shape as ClaimController::ClaimCancel().
        $validator = Validator::make($item, [
            'reason_id' => ['nullable', 'exists:close_reasons,id'],
            'other_reason' => ['required_without:reason_id'],
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException($validator->errors()->first());
        }
        // التحقق من الملف المرفوع لوحده
        if (static::requiresFile() && !$statusFile) {
            throw new \InvalidArgumentException(
                __('claim.file_is_required')
            );
        }

        if ($statusFile) {
            $fileValidator = Validator::make(
                ['cancel_file' => $statusFile],
                [
                    'cancel_file' => ['file',  'max:20480', 'mimes:'.implode(',', UploadFileType::extensionsWithoutSvg())], // 5MB
                ]
            );

            if ($fileValidator->fails()) {
                throw new \InvalidArgumentException(
                    $fileValidator->errors()->first()
                );
            }
        }

        $validated = $validator->validated();

        $additionalFile = null;

        if ($statusFile) {
            $additionalFile = $this->rejectClaimService->uploadRejectFile(
                $claim,
                $statusFile,
                $actingUserId,
                $validated['other_reason'] ?? null
            );
        }

        $this->rejectClaimService->reject($claim, $validated, $additionalFile, $actingUserId, $options);
        return true;
    }
}