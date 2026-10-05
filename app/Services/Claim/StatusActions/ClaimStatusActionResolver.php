<?php

namespace App\Services\Claim\StatusActions;

use App\Enums\ClaimStatus;
use App\Exceptions\UnsupportedStatusActionException;
use Illuminate\Contracts\Container\Container;


class ClaimStatusActionResolver
{
    private const ACTIONS = [
        ClaimStatus::APPROVED->value                => AcceptClaimStatusAction::class,
        ClaimStatus::CLOSED->value                  => CloseClaimStatusAction::class,
        ClaimStatus::BACK_TO_ORIGINAL_STATUS->value => BackToOriginalClaimStatusAction::class,
        ClaimStatus::REJECTED->value                => RejectedClaimStatusAction::class,
        ClaimStatus::COLLECTED_OUT_OF_SYSTEM->value => CollectedOutOfSystemStatusAction::class,
        ClaimStatus::LAW_FIRM->value                => LawFirmStatusAction::class,
        ClaimStatus::MANUAL_COLLECTED->value        => ManualCollectedStatusAction::class,
        ClaimStatus::CREDITED->value                => CreditedStatusAction::class,
        ClaimStatus::DEFAULT_IN_PAYMENT->value      => DefualtInPaymentStatusAction::class,
        ClaimStatus::TRANSFER_TO_ELM->value         => TransferToElmStatusAction::class,
    ];

    public function __construct(
        private readonly Container $container,
    ) {
    }

    public function resolve(string $action): ClaimStatusAction
    {
        if (!isset(self::ACTIONS[$action])) {
            throw new UnsupportedStatusActionException($action);
        }

        return $this->container->make(self::ACTIONS[$action]);
    }


      /**
     * Checked by UpdatePartnerClaimsRequest during structural
     * validation — doesn't instantiate the action class, just reads
     * its static requiresFile() flag. Unknown action names return
     * false here on purpose: resolve() is what surfaces the "unsupported
     * action" error, at the business-logic layer, not this check.
     */
    public function requiresFile(string $action): bool
    {
        if (!isset(self::ACTIONS[$action])) {
            return false;
        }
 
        return (self::ACTIONS[$action])::requiresFile();
    }
}