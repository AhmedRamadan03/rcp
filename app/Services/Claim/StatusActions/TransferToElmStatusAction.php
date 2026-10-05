<?php

namespace App\Services\Claim\StatusActions;

use App\Enums\ClaimStatus;
use App\Models\Claim;
use App\Services\Claim\ClaimActionOptions;
use App\Services\Claim\TransferToElmService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class TransferToElmStatusAction implements ClaimStatusAction
{
    public function __construct(
        private readonly TransferToElmService $service,
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
        if ($claim->status == ClaimStatus::FOLLOW_UP->value) {
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
      

        

        $this->service->execute(
            claim: $claim,
            data: $validated,
            additionalFile: null,
            actingUserId: $actingUserId,
            options: $options,
        );

        return true;
        
    }
}