<?php

namespace App\Services\ClaimImport;

use App\Actions\ClaimImportActions\AcceptClaimAction;
use App\Models\Claim;
use Exception;

class ClaimActionRouter
{

    protected array $actionMap = [
        'approved' => AcceptClaimAction::class,
        // 'rejected' => RejectClaimAction::class,
        // 'follow_up' => FollowUpClaimAction::class,
        // 'assigned' => AssignClaimAction::class,
        // 'closed' => CloseClaimAction::class,
        // 'law_firm' => TransferToLegalAction::class,
        // 'default_in_payment' => DefaultInPaymentAction::class,
    ];

    /**
     * Route a claim to the appropriate action based on status.
     *
     * @param Claim $claim The claim to process
     * @param string $status The intended status from Excel (e.g., 'approved')
     * @return void
     * @throws Exception If action fails or status is not supported
     */
    public function actionRoute(Claim $claim, string $status): void
    {
        $status = strtolower(trim($status));

        // If status is empty or 'registered', no action needed
        if (empty($status) || $status === 'registered') {
            return;
        }

        if (!isset($this->actionMap[$status])) {
            throw new Exception("Unsupported claim status for import: {$status}");
        }

        $actionClass = $this->actionMap[$status];
        $action = new $actionClass($claim);
        $action->execute();
    }

}
