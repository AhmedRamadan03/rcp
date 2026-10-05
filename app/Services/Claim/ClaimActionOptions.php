<?php

namespace App\Services\Claim;

final readonly class ClaimActionOptions
{
    public function __construct(
        public bool $requiresApproval = true,
        public bool $sendApprovalNotification = true,
    ) {}

    public static function systemApi(): self
    {
        return new self(
            requiresApproval: true,
            sendApprovalNotification: true,
        );
    }

    public static function partnerApi(): self
    {
        return new self(
            requiresApproval: false,
            sendApprovalNotification: false,
        );
    }
   
}