<?php

namespace App\Services\Claim;

use App\Enums\ClaimStatus;

use App\Models\Claim;
use App\Models\User;

use App\Services\Claim\Support\ClaimStatusActionExecutor;

class DefualtInPaymentService
{
    public function __construct(
        private readonly ClaimStatusActionExecutor $executor,
    ) {}

    public function execute(
        Claim $claim,
        array $data,
        int $actingUserId,
        ClaimActionOptions $options,
    ): bool {
        
        $user = User::findOrFail($actingUserId);
        if ($this->executor->createApprovalRequest(
            claim: $claim,
            status: ClaimStatus::DEFAULT_IN_PAYMENT->value,
            data: $data,
            additionalFile: null,
            user: $user,
            permissionKey: 'default_in_payment_manual',
            options: $options,
        )) {
             return false;
        }

        $this->executor->updateStatus(
            claim: $claim,
            status: ClaimStatus::DEFAULT_IN_PAYMENT->value,
            additionalFile: null,
            actingUserId: $actingUserId,
            data: $data,
        );

        return true;
    }
}