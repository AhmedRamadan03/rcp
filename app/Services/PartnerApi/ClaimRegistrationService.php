<?php

namespace App\Services\PartnerApi;

use App\DataTransferObjects\PartnerApi\ClaimBulkItemResult;
use App\Models\Claim;
use App\Models\User;
use App\Services\ClaimService;
use App\Services\FilesUploader;
use App\Services\ReferenceGenerationService;
use App\Traits\AttachesClaimDocuments;
use App\Traits\ClaimValidationRules;
use Illuminate\Support\Facades\Validator;

/**
 * Section 13 — POST /claims, single-item logic. Uses the SAME existing
 * services as the website (ClaimService, ReferenceGenerationService,
 * FilesUploader via AttachesClaimDocuments) — nothing new invented.
 */
class ClaimRegistrationService
{
    use AttachesClaimDocuments;
    use ClaimValidationRules;

    public function __construct(
        public ClaimService $claimService,
        public FilesUploader $filesUploader,
        private readonly ReferenceGenerationService $referenceService,
    ) {
    }

    /**
     * @param array &$externalReferencesInBatch Tracks externalReference
     *        values already used earlier in this same request batch.
     * @param array<int, array{file: \Illuminate\Http\UploadedFile, title: ?string, access: ?string}> $documents
     */
    public function register(
        array $item,
        string $requestItemId,
        array &$externalReferencesInBatch,
        ?int $userId,
        array $documents = []
    ): ClaimBulkItemResult {
        $externalReference = $item['externalReference'] ?? ($item['external_reference'] ?? null);

        // Same trimming ClaimController::store() applies before validating.
        $item['claim_number'] = isset($item['claim_number']) ? trim((string) $item['claim_number']) : $item['claim_number'] ?? null;
        $item['debtor_phone'] = !empty($item['debtor_phone']) ? trim((string) $item['debtor_phone']) : null;
        $item['debtor_nid'] = isset($item['debtor_nid']) ? trim((string) $item['debtor_nid']) : $item['debtor_nid'] ?? null;
        $item['sub_claim_number'] = !empty($item['sub_claim_number']) ? trim((string) $item['sub_claim_number']) : null;

        $validator = Validator::make($item, $this->claimValidationRules($item, 'store'));

        if ($validator->fails()) {
            return ClaimBulkItemResult::failure($requestItemId, null, $externalReference, $validator->errors()->first());
        }

        $validated = $validator->validated();

        // Same duplicate check as the website — claim_number +
        // debtor_nid + amount (+ sub_claim_number if present).
        $isDuplicate = $this->claimService->isDuplicateClaim(
            $validated['claim_number'],
            $validated['debtor_nid'],
            $validated['amount'],
            $validated['sub_claim_number'] ?? null
        )->exists();

        if ($isDuplicate) {
            return ClaimBulkItemResult::failure(
                $requestItemId,
                null,
                $externalReference,
                'Claim with this claim number and sub-claim number already exists.'
            );
        }

        if (!empty($validated['external_reference'])) {
            $existsInDb = Claim::where('external_reference', $validated['external_reference'])->exists();
            if ($existsInDb) {
                return ClaimBulkItemResult::failure(
                    $requestItemId,
                    null,
                    $externalReference,
                    'Claim with this external reference already exists.',
                    409
                );
            }

            if (in_array($validated['external_reference'], $externalReferencesInBatch, true)) {
                return ClaimBulkItemResult::failure(
                    $requestItemId,
                    null,
                    $externalReference,
                    'Duplicate externalReference within this request.',
                    409
                );
            }

            $externalReferencesInBatch[] = $validated['external_reference'];
        }

        $user = User::where('email',$item['user_email'])->first();
        $validated['user_id'] = $user->id;
        $validated['client_id'] = $user->client_id;
        $validated['return_reason'] = $item['return_reason_id'] ?? null;

        $claim = $this->claimService->create($validated);

        $this->referenceService->generateForNewClaim(function (string $reference) use ($claim) {
            $claim->reference = $reference;
            $claim->save();
        });

        // Matches uploadFile()'s behavior: this event only fires when
        // a document was actually attached, not on every claim creation.
        if ($this->attachDocuments($claim, $documents, $user->id)) {
            event(new \App\Events\ClaimInfo\UpdateClaimInstallmentEvent($claim->id));
        }

        return ClaimBulkItemResult::success($requestItemId, $claim->fresh(), 'Claim registered successfully.');
    }
}