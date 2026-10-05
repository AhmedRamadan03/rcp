<?php

namespace App\Actions\ClaimImportActions;

use App\Enums\ClaimStatus;
use App\Models\Assigning;
use App\Models\Claim;
use App\Models\CloseClaim;
use App\Models\CloseReason;
use App\Models\UploadedClaimsFile;
use App\Services\ClaimService;
use Exception;


class CloseClaimAction
{
    public function __construct(
        public Claim $claim,
        public ?array $rowData
    ) {}

    public function execute(): void
    {

        $claim = $this->claim;
        // create payment for the claim
        (new CreatePaymentAction($claim, $this->rowData))->execute();

        $previous_status = $claim->status ?? ClaimStatus::REGISTERED->value;

        $close_reason = CloseReason::create(['reason' => $this->rowData['close_reason']]);

        $url = UploadedClaimsFile::where('id', $this->claim->file_id)->first()->file_url ?? 'inserted_by_import';

        $close_claim = CloseClaim::create([
                    'claim_id' => $claim->id,
                    'reason_id' => $close_reason->id,
                    'url' => $url,
                ]);

        $claim->status = ClaimStatus::CLOSED->value;
        $claim->is_close = 1;

        $claim->save();

        (new ClaimService())->writeToClaimStatusHistory($claim->id, $previous_status, $claim->status, $claim->user_id);
    }
}
