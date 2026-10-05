<?php

namespace App\Services\Claim\Support;

use App\Enums\FileVisibility;
use App\Models\AdditionalFile;
use App\Models\Claim;
use App\Models\User;
use App\Services\Claim\ClaimActionOptions;
use App\Services\ClaimService;

class ClaimStatusActionExecutor
{
    public function __construct(
        private readonly ClaimService $claimService,
    ) {}

    public function createApprovalRequest(
        Claim $claim,
        string $status,
        array $data,
        ?AdditionalFile $additionalFile,
        User $user,
        string $permissionKey,
        ClaimActionOptions $options,
    ) {
        return $this->claimService->createRequestStatus(
            claim_id:$claim->id,
            status:$status,
            request:$data,
            additional_file:$additionalFile,
            permission_key:$permissionKey,
            permission_path:'claim_status',
            user:$user,
            options:$options,
        );
    }

    public function updateStatus(
        Claim $claim,
        string $status,
        ?AdditionalFile $additionalFile,
        int $actingUserId,
        array $data = [],
    ): void {
        $previousStatus = $claim->status;

        $claim->update([
            'status' => $status,
        ]);

        if ($additionalFile) {
            $additionalFile->update([
                'attachment_status' => FileVisibility::PUBLISHED->value,
            ]);
            
            $data['file_id'] = $additionalFile->file_id;
        }


        $this->claimService->writeToClaimStatusHistory(
            $claim->id,
            $previousStatus,
            $status,
            $actingUserId,
            $data,
        );
    }
}
