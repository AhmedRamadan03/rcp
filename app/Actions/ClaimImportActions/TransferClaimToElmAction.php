<?php

namespace App\Actions\ClaimImportActions;

use App\Enums\ClaimStatus;
use App\Enums\SmsStatus;
use App\Models\Claim;
use App\Models\ElmRequest;
use App\Services\ClaimService;
use App\Services\ElmService;



class TransferClaimToElmAction
{
    public function __construct(
        public Claim $claim,
        public ?array $rowData
    ) {}

    public function execute(): void
    {
        $claim = $this->claim;
        $previous_status = $claim->status ?? ClaimStatus::REGISTERED->value;

        (new AcceptClaimAction($claim, $this->rowData))->execute();

        $payment_link = $claim->payments->first()->links->first();

        // create elm request with skipped status and skip sending message with elm service 
        $elmRequest = new ElmRequest;
        $elmRequest->claim_id = $claim->id;
        $elmRequest->national_id = $claim->debtor_nid;
        $elmRequest->link = $payment_link->link;
        $elmRequest->status = SmsStatus::SKIPPED->value;
        $elmRequest->save();

        // update claim status
        $claim->status = ClaimStatus::TRANSFER_TO_ELM->value;
        $claim->is_draft = false;
        $claim->save();

        (new ClaimService())->writeToClaimStatusHistory($claim->id, $previous_status, $claim->status, $claim->user_id);
    }
}
