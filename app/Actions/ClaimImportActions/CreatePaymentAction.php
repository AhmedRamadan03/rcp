<?php

namespace App\Actions\ClaimImportActions;

use App\Enums\ClaimStatus;
use App\Models\Claim;
use App\Models\Payment;
use Carbon\Carbon;
use Exception;


class CreatePaymentAction
{
    public function __construct(
        public Claim $claim,
        public ?array $rowData
    ) {}

    public function execute(): void
    {
        try {
            $claim = $this->claim;
    
            $status = $this->rowData['claim_status'];
            
            $data = [
                'claim_id' => $claim->id,
                'amount' => $claim->amount,
            ];



            if ($status === ClaimStatus::MANUAL_COLLECTED->value) {
                $data['paid_amount'] = $this->claim->amount;
            } elseif (in_array($status, [ClaimStatus::PARTIAL->value, ClaimStatus::DELAY->value])) {
                $data['paid_amount'] = $this->rowData['recovered_amount'] ?? 0.00;
            }

            $payment = Payment::create($data);


            $fullPaymentLinkData = [
                'payment_id' => $payment->id,
                'amount' =>  $payment->amount,
                'due_date' => $claim->intimation_date ?? Carbon::today(),
                'order' => 1,
            ];

        (new CreatePaymentLinkAction($fullPaymentLinkData))->execute();

        } catch (Exception $exception) {
            throw new Exception($exception->getMessage());
        }
    }
}
