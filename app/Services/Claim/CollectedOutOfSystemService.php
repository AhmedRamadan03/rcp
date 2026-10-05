<?php

namespace App\Services\Claim;

use App\Enums\ClaimStatus;
use App\Enums\FileVisibility;
use App\Models\AdditionalFile;
use App\Models\Claim;
use App\Models\User;
use App\Services\Claim\Support\ClaimStatusActionExecutor;
use Illuminate\Support\Facades\DB;

class CollectedOutOfSystemService
{
    public function __construct(
        private readonly ClaimStatusActionExecutor $executor,
    ) {}

    public function execute(
        Claim $claim,
        array $data,
        ?AdditionalFile $additionalFile,
        int $actingUserId,
        ClaimActionOptions $options,
    ): bool {
        if ($claim->status !== ClaimStatus::REGISTERED->value) {
            throw new \Exception('Claim is not outstanding');
        }
        $user = User::findOrFail($actingUserId);
        if ($this->executor->createApprovalRequest(
            claim: $claim,
            status: ClaimStatus::COLLECTED_OUT_OF_SYSTEM->value,
            data: $data,
            additionalFile: $additionalFile,
            user: $user,
            permissionKey: 'collected_out_of_system',
            options: $options,
        )) {
            return false;
        }

        DB::transaction(function () use ($claim, $additionalFile, $actingUserId, $data) {
            $this->executor->updateStatus(
                claim: $claim,
                status: ClaimStatus::COLLECTED_OUT_OF_SYSTEM->value,
                additionalFile: $additionalFile,
                actingUserId: $actingUserId,
                data: $data,
            );
        });

        return true;
    }
}