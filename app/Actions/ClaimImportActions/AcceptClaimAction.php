<?php

namespace App\Actions\ClaimImportActions;

use App\Enums\ClaimStatus;
use App\Enums\MessageTemplates;
use App\Models\Assigning;
use App\Models\Claim;
use App\Services\ClaimService;
use App\Services\MessageService;
use App\Services\SmsService;
use Exception;


class AcceptClaimAction
{
    public function __construct(
        public Claim $claim,
        public ?array $rowData
    ) {}

    public function execute(): void
    {
        $claim = $this->claim;
        $previous_status = $claim->status ?? ClaimStatus::REGISTERED->value;

        $user = auth()->user();

        if ($claim->status == ClaimStatus::APPROVED->value) {
            throw new Exception('This claim is already approved');
        }

        if ($claim->assignees()->count() == 0) {
            Assigning::create([
                'claim_id' => $claim->id,
                'to' => $user->id,
                'supervisor_id' => $user->id,
                'is_active' => 1,
            ]);
        }

        (new CreatePaymentAction($claim, $this->rowData))->execute();

        $claim->status = ClaimStatus::APPROVED->value;
        $claim->is_draft = false;
        $claim->save();




        (new ClaimService())->writeToClaimStatusHistory($claim->id, $previous_status, $claim->status, $claim->user_id);
    }
}
