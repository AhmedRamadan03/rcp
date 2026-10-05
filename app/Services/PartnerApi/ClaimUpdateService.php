<?php

namespace App\Services\PartnerApi;

use App\DataTransferObjects\PartnerApi\ClaimBulkItemResult;
use App\Exceptions\UnsupportedStatusActionException;
use App\Models\Claim;
use App\Models\User;
use App\Services\Claim\ClaimActionOptions;
use App\Services\ClaimService;
use App\Services\FilesUploader;
use App\Services\Claim\StatusActions\ClaimStatusActionResolver;
use App\Traits\AttachesClaimDocuments;
use App\Traits\ClaimValidationRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Section 14 — PATCH /claims, single-item logic. Currently handles
 * CLAIM INFORMATION fields + `status` actions + documents.
 *
 * NOT YET IMPLEMENTED — payment data, SADAD (`transation` table).
 */
class ClaimUpdateService
{
    use AttachesClaimDocuments;
    use ClaimValidationRules;

    public function __construct(
        public ClaimService $claimService,
        public FilesUploader $filesUploader,
        private readonly ClaimStatusActionResolver $statusActionResolver,
    ) {
    }

    /**
     * @param array &$externalReferencesInBatch
     * @param array<int, array{file: \Illuminate\Http\UploadedFile, title: ?string, access: ?string}> $documents
     * @param \Illuminate\Http\UploadedFile|null $statusFile Single file
     *        specific to the `status` action itself (e.g. close's
     *        `close_file`) — separate from $documents.
     */
    public function update(
        array $item,
        string $reference,
        array &$externalReferencesInBatch,
        ?int $userId,
        array $documents = [],
        ?\Illuminate\Http\UploadedFile $statusFile = null
    ): ClaimBulkItemResult {
        $externalReference = $item['externalReference'] ?? ($item['external_reference'] ?? null);

        $claim = Claim::where('reference', $reference)->first();

        if (!$claim) {
            return ClaimBulkItemResult::failure(null, $reference, $externalReference, 'Claim not found.', 404);
        }

        // Legacy 'city_id' === 'undefined' quirk from the website's update().
        if (isset($item['city_id']) && $item['city_id'] === 'undefined') {
            $item['city_id'] = null;
        }

        // TRUE partial validation: only build rules for fields the
        // partner actually submitted on this item.
        $allRules = $this->claimValidationRules($item, 'update');
        $submittedFields = array_diff(array_keys($item), ['reference', 'requestItemId', 'status']);
        $rulesToApply = collect($allRules)->only($submittedFields)->all();

        $validator = Validator::make($item, $rulesToApply);

        if ($validator->fails()) {
            return ClaimBulkItemResult::failure(null, $reference, $externalReference, $validator->errors()->first());
        }

        $validated = $validator->validated();

        if (array_key_exists('return_reason_id', $item)) {
            $validated['return_reason'] = $item['return_reason_id'];
        }

        // Same duplicate check as the website's update(), excluding
        // this claim's own row.
        if (isset($validated['claim_number']) || isset($validated['debtor_nid']) || isset($validated['amount']) || isset($validated['sub_claim_number'])) {
            $checkClaimNumber = $validated['claim_number'] ?? $claim->claim_number;
            $checkDebtorNid = $validated['debtor_nid'] ?? $claim->debtor_nid;
            $checkAmount = $validated['amount'] ?? $claim->amount;
            $checkSubClaimNumber = $validated['sub_claim_number'] ?? $claim->sub_claim_number;

            $isDuplicate = $this->claimService->isDuplicateClaim(
                $checkClaimNumber,
                $checkDebtorNid,
                $checkAmount,
                $checkSubClaimNumber
            )->where('id', '<>', $claim->id)->exists();

            if ($isDuplicate) {
                return ClaimBulkItemResult::failure(
                    null,
                    $reference,
                    $externalReference,
                    'Claim with this claim number and sub-claim number already exists.'
                );
            }
        }

        // Same external_reference uniqueness check, excluding this
        // claim's own row (client scoping removed — single-tenant DB).
        if (!empty($validated['external_reference'])) {
            $existsInDb = Claim::where('external_reference', $validated['external_reference'])
                ->where('id', '<>', $claim->id)
                ->exists();

            if ($existsInDb) {
                return ClaimBulkItemResult::failure(
                    null,
                    $reference,
                    $externalReference,
                    'Claim with this external reference already exists.',
                    409
                );
            }

            if (in_array($validated['external_reference'], $externalReferencesInBatch, true)) {
                return ClaimBulkItemResult::failure(
                    null,
                    $reference,
                    $externalReference,
                    'Duplicate externalReference within this request.',
                    409
                );
            }
            $externalReferencesInBatch[] = $validated['external_reference'];
        }

        // Everything from here on MUTATES the database — field update,
        // amount change, documents, AND the status action — all run
        // inside ONE transaction, with status action LAST as requested.
        // If the status action (or anything before it) throws, the
        // exception is left to propagate out of the closure, which is
        // what makes DB::transaction() roll back EVERYTHING in this
        // item automatically — including the field/amount updates that
        // ran earlier in the same transaction. It's caught OUTSIDE the
        // transaction below, once rollback has already happened, purely
        // to turn it into a ClaimBulkItemResult::failure().
        $result = null;

        try {
            DB::transaction(function () use ($claim, $item, $validated, $documents, $userId, $statusFile, &$result) {
                $isAmountChanged = isset($validated['amount']) && (float) $validated['amount'] != (float) $claim->amount;
                $user = User::findOrFail($userId);
                if ($isAmountChanged) {
                    $changeAmountData = [
                        'new_amount' => $validated['amount'],
                        'current_amount' => $claim->amount,
                        'note' => null,
                    ];
                    unset($validated['amount']);
                   $result = $this->claimService->changeAmount($claim, $changeAmountData, $user, ClaimActionOptions::partnerApi());
                }

                if (count($validated) > 0) {
                    $claim->update($validated);
                }

                $this->attachDocuments($claim, $documents, $userId);

                // Matches the website's update(): fires exactly once
                // per item, regardless of whether documents were also
                // attached.
                event(new \App\Events\ClaimInfo\UpdateClaimInstallmentEvent($claim->id));
                
                // Status action LAST, as requested. NOT caught here —
                // a failure here must roll back everything above too.
                if (array_key_exists('status', $item)) {
                    $result = $this->statusActionResolver->resolve($item['status'])->handle(claim:$claim, item:$item,actingUserId: $userId,options: ClaimActionOptions::partnerApi(), statusFile:$statusFile);
                }
            });
        } catch (UnsupportedStatusActionException $e) {
            return ClaimBulkItemResult::failure(null, $reference, $externalReference, $e->getMessage(), 422);
        } catch (\Throwable $e) {
            // The transaction has already been rolled back by this
            // point — nothing from this item was persisted, even
            // though $claim's in-memory attributes may still look
            // mutated (that's just PHP object state, not the DB row).
            return ClaimBulkItemResult::failure(null, $reference, $externalReference, $e->getMessage(), 422);
        }

        return ClaimBulkItemResult::success(null, $claim->fresh(),$result != ""? 'Claim updated successfully.': __('claim.request_has_been_sent_successfully'));
    }
}