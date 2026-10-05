<?php

namespace App\Services\Claim\StatusActions;

use App\Models\Claim;
use App\Models\User;
use App\Services\Claim\ClaimActionOptions;
use App\Services\ClaimService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

/**
 * Mirrors ClaimInfoController::claimAccept() exactly:
 *   $claimService->accept($claim);
 *
 * ClaimService::accept() internally calls auth()->user() (for
 * assigning the claim and writing claim_status_history) — which is
 * always null on a Partner API request, since that request is
 * authenticated via X-Client-Id/X-Client-Secret, not a logged-in user.
 * Without the wrapper below, this crashes with a fatal error on
 * `auth()->user()->id` every single time, unconditionally.
 *
 * Auth::onceUsingId() authenticates auth()->user() for THIS REQUEST
 * ONLY (nothing is written to the session), so accept()'s internal
 * auth()->user() calls resolve correctly without any change to
 * ClaimService itself — the shared method the website uses stays
 * completely untouched.
 */
class AcceptClaimStatusAction implements ClaimStatusAction
{
    public function __construct(
        private readonly ClaimService $claimService,
    ) {}
    
    public static function requiresFile(): bool
    {
        return false;
    }
    
    public function handle(Claim $claim, array $item, ?int $actingUserId, ClaimActionOptions $options, ?UploadedFile $statusFile = null): ?string
    {
        if ($actingUserId === null) {
            throw new \RuntimeException(
                'Cannot accept this claim: no internal user is associated with this API credential.'
            );
        }
        $user = User::findOrFail($actingUserId);

        return $this->claimService->accept($claim,$user);
    }
}