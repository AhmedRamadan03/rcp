<?php

namespace App\Services\Claim;

use App\Enums\ClaimStatus;

use App\Models\Claim;
use App\Models\User;
use RuntimeException;

use App\Services\Claim\Support\ClaimStatusActionExecutor;

class CreditedStatusService
{
    public function __construct(
        private readonly ClaimStatusActionExecutor $executor,
    ) {}

    public function execute(
        Claim $claim,
        int $actingUserId,
        ClaimActionOptions $options,
    ): bool {
        
        $user = User::findOrFail($actingUserId);
        if ($this->executor->createApprovalRequest(
            claim: $claim,
            status: ClaimStatus::CREDITED->value,
            data: [],
            additionalFile: null,
            user: $user,
            permissionKey: 'collected_credited',
            options: $options,
        )) {
             return false;
        }

        $this->executor->updateStatus(
            claim: $claim,
            status: ClaimStatus::CREDITED->value,
            additionalFile: null,
            actingUserId: $actingUserId,
            data: [],
        );

        return true;
    }
}