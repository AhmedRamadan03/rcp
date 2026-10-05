<?php

namespace App\Services\Claim\StatusActions;

use App\Models\Claim;
use App\Services\Claim\ClaimActionOptions;
use Illuminate\Http\UploadedFile;

/**
 * Section 14 "status" field: each supported action name implements
 * this interface in its own class. Adding a new status action never
 * touches the controller or the update service — only:
 *   1. a new class implementing this interface
 *   2. one line registering it in ClaimStatusActionResolver::ACTIONS
 */
interface ClaimStatusAction
{
     /**
     * Whether this action REQUIRES a statusFile to be present in the
     * request. Checked structurally by UpdatePartnerClaimsRequest
     * before this class's handle() ever runs, so a missing required
     * file fails as a clean 422 at the request-validation layer
     * instead of a business-logic exception later.
     */
    public static function requiresFile(): bool;
    /**
     * @param array $item The raw submitted item, in case the action
     *        needs extra fields beyond the claim itself (e.g. a reason,
     *        a note — mirroring what the equivalent website endpoint
     *        accepts).
     * @param int|null $actingUserId The internal RCP user the action is
     *        performed "as" — the credential's `created_by`, since
     *        Partner API requests have no real auth()->user(). Several
     *        underlying ClaimService methods (accept(), close(), etc.)
     *        call auth()->user() internally, so implementations that
     *        call such methods MUST wrap the call in
     *        Auth::onceUsingId($actingUserId) first.
     * @param UploadedFile|null $statusFile A SINGLE file specific to
     *        this action (e.g. close's `close_file`), submitted via
     *        statusFile[{reference}] — NOT the same as the general
     *        documents[{reference}][] array, since action files often
     *        map to a different model/table (AdditionalFile, not
     *        ClaimFile) with action-specific fields. Actions that
     *        don't need a file simply ignore this parameter.
     *
     * @throws \Throwable on any business-rule failure. The message is
     *         surfaced to the partner as-is at 422, matching how the
     *         website's own status-change endpoints behave.
     */
    public function handle(Claim $claim,array $item,?int $actingUserId,ClaimActionOptions $options, ?UploadedFile $statusFile = null): ?string;
}