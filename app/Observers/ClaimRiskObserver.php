<?php
namespace App\Observers;

use App\Jobs\CalculateClaimRiskJob;
use App\Models\Claim;

class ClaimRiskObserver
{
    public function created(Claim $claim): void
    {
        dispatch(new CalculateClaimRiskJob($claim, auth()->id()));
    }

    public function updated(Claim $claim): void
    {
        $riskFields = ['amount', 'type', 'debtor_type', 'accident_date', 'status', 'city_id', 'debtor_nid', 'claim_number', 'client_id'];
        if ($claim->wasChanged($riskFields)) {
            dispatch(new CalculateClaimRiskJob($claim, auth()->id()));
        }
    }
}