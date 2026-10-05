<?php

namespace App\Services\Claim\StatusActions;

use App\Models\Claim;
use App\Services\Claim\BackToOriginalStatusService;
use App\Services\Claim\ClaimActionOptions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

/**
 * Restores the claim to its previous status based on the latest
 * claim status history record.
 *
 * This action is intended for Partner API requests, where there is
 * no authenticated web user by default. The acting user ID must be
 * provided from the API credentials and is temporarily authenticated
 * using Auth::onceUsingId() so that any internal authentication-dependent
 * logic can resolve the correct user for the current request only.
 *
 * The claim can only be reverted from specific statuses and backlog
 * claims are not allowed to use this action.
 *
 * The previous status is retrieved from the latest ClaimStatusHistory
 * record, then:
 * - The claim status is restored to the previous status.
 * - The status change is recorded in claim_status_history.
 * - The claim is marked as not closed.
 *
 * No session authentication is persisted by this action.
 */
class BackToOriginalClaimStatusAction implements ClaimStatusAction
{
    public function __construct(
        private readonly BackToOriginalStatusService $service,
    ) {}

    public static function requiresFile(): bool
    {
        return false;
    }

    public function handle(Claim $claim,array $item,?int $actingUserId,ClaimActionOptions $options,?UploadedFile $statusFile = null): ?string {

        if ($actingUserId === null) {
            throw new \RuntimeException(
                'Cannot restore claim status: no internal user is associated with this API credential.'
            );
        }
        
        return $this->service->execute(
            $claim,
            $options,
            $actingUserId
        );
    }
}