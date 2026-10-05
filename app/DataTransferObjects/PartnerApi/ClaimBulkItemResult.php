<?php

namespace App\DataTransferObjects\PartnerApi;

use App\Models\Claim;

/**
 * Shape defined by Section 13.3's example response item, reused for
 * both POST /claims and PATCH /claims (Section 17: per-item results).
 *
 * `requestItemId` is only meaningful for POST /claims — PATCH has no
 * such field, so it's null there and simply omitted from toArray().
 *
 * `claim` is intentionally NOT part of toArray()'s output — it's kept
 * only so the controller can pass the freshly created/updated Claim
 * straight into document attachment without a second DB lookup.
 */
final class ClaimBulkItemResult
{
    private function __construct(
        public readonly ?string $requestItemId,
        public readonly ?string $reference,
        public readonly ?string $externalReference,
        public readonly bool $success,
        public readonly string $message,
        public readonly ?array $error,
        public readonly ?Claim $claim = null,
    ) {
    }

    public static function success(?string $requestItemId, Claim $claim, string $message): self
    {
        return new self(
            requestItemId: $requestItemId,
            reference: $claim->reference,
            externalReference: $claim->external_reference,
            success: true,
            message: $message,
            error: null,
            claim: $claim,
        );
    }

    public static function failure(
        ?string $requestItemId,
        ?string $reference,
        ?string $externalReference,
        string $errorMessage,
        int $statusCode = 422,
    ): self {
        return new self(
            requestItemId: $requestItemId,
            reference: $reference,
            externalReference: $externalReference,
            success: false,
            message: '',
            error: ['statusCode' => $statusCode, 'message' => $errorMessage],
        );
    }

    public function toArray(): array
    {
        $data = [
            'reference' => $this->reference,
            'externalReference' => $this->externalReference,
            'result' => [
                'success' => $this->success,
                'message' => $this->message,
                'error' => $this->error,
            ],
        ];

        if ($this->requestItemId !== null) {
            $data = ['requestItemId' => $this->requestItemId] + $data;
        }

        return $data;
    }
}