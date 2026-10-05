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


class LawClaimAction
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

        $claim->status = ClaimStatus::LAW_FIRM->value;
        $claim->is_draft = false;
        $claim->save();

        $message = (new MessageService())->convertTemplate(MessageTemplates::TRANSFER_TO_LAWYER->value, $claim->id);
        (new SmsService())->sendSms($claim->debtor_phone, $message, $claim->id, MessageTemplates::TRANSFER_TO_LAWYER->value, template_model_id: $claim->id);



        (new ClaimService())->writeToClaimStatusHistory($claim->id, $previous_status, $claim->status, $claim->user_id);
    }
}
