<?php

namespace App\Services\Claim\StatusActions;

use App\Enums\ClaimStatus;
use App\Models\Claim;
use App\Services\Claim\ClaimActionOptions;
use App\Services\Claim\CreditedStatusService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class CreditedStatusAction implements ClaimStatusAction
{
    public function __construct(
        private readonly CreditedStatusService $service,
    ) {}

    public static function requiresFile(): bool
    {
        return false;
    }

    public function handle(Claim $claim,array $item,?int $actingUserId,ClaimActionOptions $options,?UploadedFile $statusFile = null): ?string {
       
        if ($actingUserId === null) {
            throw new RuntimeException(
                'Cannot collect claim: no internal user is associated with this API credential.'
            );
        }
        if ($claim->status !== ClaimStatus::MANUAL_COLLECTED->value && $claim->status !== ClaimStatus::DONE->value) {
            throw new \Exception('You Can not Perform This Action');
        }
        if (!$claim->is_collected) {
            throw new \Exception(__('claim.claim_is_not_paid'));
        }

        return $this->service->execute(claim: $claim,actingUserId: $actingUserId,options: $options);

    }
}