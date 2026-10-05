<?php

namespace App\Services\Claim\StatusActions;

use App\Enums\ClaimStatus;
use App\Models\Claim;
use App\Services\Claim\ClaimActionOptions;
use App\Services\Claim\DefualtInPaymentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class DefualtInPaymentStatusAction implements ClaimStatusAction
{
    public function __construct(
        private readonly DefualtInPaymentService $service,
    ) {}

    public static function requiresFile(): bool
    {
        return false;
    }

    public function handle(Claim $claim,array $item,?int $actingUserId,ClaimActionOptions $options,?UploadedFile $statusFile = null): ?string {
       
        if ($actingUserId === null) {
            throw new RuntimeException(
                'Cannot collect claim: no internal user is associated with this API credential.'
            );
        }
        if (
                $claim->status !== ClaimStatus::FOLLOW_UP->value &&
                $claim->status !== ClaimStatus::PARTIAL->value &&
                $claim->status !== ClaimStatus::DEFAULT_IN_PAYMENT->value &&
                $claim->status !== ClaimStatus::DEFAULT_IN_PAYMENT_BY_SYSTEM->value &&
                $claim->status !== ClaimStatus::DELAY->value &&
                $claim->status !== ClaimStatus::APPROVED->value &&
                $claim->status !== ClaimStatus::MANUAL_COLLECTED->value &&
                $claim->status !== ClaimStatus::DONE->value
        ) {
            throw new \Exception('You Can not Perform This Action');
        }
        
        $validator = Validator::make($item, [
            'note' => 'required',
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException(
                $validator->errors()->first()
            );
        }

        $validated = $validator->validated();
      

        

        return $this->service->execute(
            claim: $claim,
            data: $validated,
            actingUserId: $actingUserId,
            options: $options,
        );
        
    }
}