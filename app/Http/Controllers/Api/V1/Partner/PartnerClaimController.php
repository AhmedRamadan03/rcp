<?php

namespace App\Http\Controllers\Api\V1\Partner;

use App\DataTransferObjects\PartnerApi\ClaimBulkItemResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Claims\MakeInstallmentRequest;
use App\Http\Requests\V1\Claims\PayInstallmentRequest;
use App\Http\Requests\V1\Partner\StorePartnerClaimsRequest;
use App\Http\Requests\V1\Partner\UpdatePartnerClaimsRequest;
use App\Http\Resources\Api\V1\ClaimResource;
use App\Http\Resources\Api\V1\Collections\Collection;
use App\Models\Claim;
use App\Models\User;
use App\Services\Claim\ClaimActionOptions;
use App\Services\Claim\InstallmentService;
use App\Services\ClaimService;
use App\Services\PartnerApi\ClaimRegistrationService;
use App\Services\PartnerApi\ClaimUpdateService;
use App\Traits\ClaimFilters;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

/**
 * Deliberately thin: request parsing + looping + response shaping only.
 * All business logic lives in ClaimRegistrationService / ClaimUpdateService
 * (Section 13 / 14), so this controller doesn't grow as more status
 * actions or fields are added.
 */
class PartnerClaimController extends Controller
{
    use ClaimFilters;

    public function __construct(
        private readonly ClaimRegistrationService $registrationService,
        private readonly ClaimUpdateService $updateService,
        private readonly InstallmentService $installmentService,
        private readonly ClaimService $claimService,
    ) {
    }

    /**
     * GET /claims — Section 16.
     */
    public function index(Request $request)
    {
        $filters = $this->collectFilters($request);

        $query = $this->baseQuery();

        $this->applyAllFilters($query, $filters);

        $claims = $this->handleResult($query, true, $request->paginate ?? 10, false);

        return response()->json(
            new Collection($claims, 'Claims retrieved successfully.', ClaimResource::class)
        );
    }

    /**
     * GET /claims/{reference} — Section 15.
     */
    public function show(string $reference)
    {
        $claim = Claim::where('reference', $reference)->first();

        if (!$claim) {
            return response()->json([
                'success' => false,
                'message' => 'Claim not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Claim successfully shown.',
            'data' => new ClaimResource($claim),
        ]);
    }

    /**
     * POST /claims — Section 13. Bulk registration.
     */
    public function store(StorePartnerClaimsRequest $request)
    {
        $claims = $request->input('claims', []);
        $uploadedBy = $request->attributes->get('partner_api_credential')?->created_by;

        $results = [];
        $externalReferencesInBatch = [];

        foreach ($claims as $item) {
            $requestItemId = $item['requestItemId'];
            $result = $this->registrationService->register(
                $item,
                $requestItemId,
                $externalReferencesInBatch,
                $uploadedBy,
                $this->extractDocuments($request, $requestItemId)
            );

            $results[] = $result->toArray();
        }

        return $this->bulkResponse(
            $results,
            'Claims processed successfully.',
            'Claims processed with one or more errors.'
        );
    }

    /**
     * PATCH /claims — Section 14. Bulk partial updates.
     */
    public function patch(UpdatePartnerClaimsRequest $request)
    {
        $claims = $request->input('claims', []);
       

        $results = [];
        $externalReferencesInBatch = [];

        foreach ($claims as $item) {
            $reference = $item['reference'];
            $user = User::where('email',$item['user_email'])->first();
            $result = $this->updateService->update(
                $item,
                $reference,
                $externalReferencesInBatch,
                $user->id,
                $this->extractDocuments($request, $reference),
                $request->file("statusFile.{$reference}")
            );

            $results[] = $result->toArray();
        }

        return $this->bulkResponse(
            $results,
            'Claims updated successfully.',
            'Claims processed with one or more errors.'
        );
    }


    public function showInstallment($reference)
    {
        $claim = Claim::where('reference',$reference)->first();
        if (!$claim) {
                return response()->json([
                'success' => false,
                'message' => 'Claim Not Found',
            ],404);
        }
        try {
            $installmentData = $this->claimService->getClaimInstallments($claim->id);

            return response()->json([
                'success' => true,
                'message' => 'Claim installments retrieved successfully',
                'data' => $installmentData
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ], 400);
        }

    }

    public function makeInstallment(MakeInstallmentRequest $request, $reference)
    {
        try{

            $claim = Claim::where('reference',$reference)->first();
            if (!$claim) {
                 return response()->json([
                    'success' => false,
                    'message' => 'Claim Not Found',
                ],404);
            }
            $uploadedBy = $request->attributes->get('partner_api_credential')?->created_by;
    
            $result = $this->installmentService->create(
                claim: $claim,
                installments: $request->validated('installments'),
                note: $request->validated('note'),
                actingUserId: $uploadedBy,
                options: ClaimActionOptions::partnerApi()
            );
    
            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Installments Created Successfully',
                ]);
            }
    
            return response()->json([
                'success' => true,
                'message' => __('claim.request_has_been_sent_successfully'),
            ], 200);
        } catch (\Throwable $e) {
            // The transaction has already been rolled back by this
            // point — nothing from this item was persisted, even
            // though $claim's in-memory attributes may still look
            // mutated (that's just PHP object state, not the DB row).
             return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
    
    public function updateInstallment(MakeInstallmentRequest $request, $reference)
    {
        try {
            $claim = Claim::where('reference', $reference)->first();
    
            if (!$claim) {
                return response()->json([
                    'success' => false,
                    'message' => 'Claim Not Found',
                ], 404);
            }
    
            $uploadedBy = $request->attributes->get('partner_api_credential')?->created_by;
    
            $result = $this->installmentService->update(
                claim: $claim,
                installments: $request->validated('installments'),
                note: $request->validated('note'),
                actingUserId: $uploadedBy,
                options: ClaimActionOptions::partnerApi()
            );
    
            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Installments updated successfully', 
                ]);
            }
    
            return response()->json([
                'success' => true,
                'message' => __('claim.request_has_been_sent_successfully'),
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function deleteInstallment(Request $request, $reference)
    {
        try{
            $claim = Claim::where('reference',$reference)->first();
            if (!$claim) {
                    return response()->json([
                    'success' => false,
                    'message' => 'Claim Not Found',
                ],404);
            }

            $uploadedBy = $request->attributes->get('partner_api_credential')?->created_by;

            $result = $this->installmentService->delete(
                claim: $claim,
                actingUserId: $uploadedBy,
                options: ClaimActionOptions::partnerApi()
            );
            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Installments Deleted Successfully',
                ]);
            }
    
            return response()->json([
                'success' => true,
                'message' => __('claim.request_has_been_sent_successfully'),
            ], 200);
        } catch (\Throwable $e) {
            // The transaction has already been rolled back by this
            // point — nothing from this item was persisted, even
            // though $claim's in-memory attributes may still look
            // mutated (that's just PHP object state, not the DB row).
             return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

    }


    public function payInstallment(PayInstallmentRequest $request, $reference, $installment_id)
    {
        try {
            $claim = Claim::where('reference',$reference)->first();
            if (!$claim) {
                    return response()->json([
                    'success' => false,
                    'message' => 'Claim Not Found',
                ],404);
            }
            $userAssign = $claim?->assignee;

            if (!$userAssign) {
                $userAssign = User::where('email', $request->validated('user_email'))->first();

                if (!$userAssign) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No user found with the provided email.',
                    ], 404);
                }
            }
            $this->installmentService->payInstallment(
                    claim: $claim,
                    linkId: $installment_id,
                    data: $request->validated(),
                    actingUserId: $userAssign->to,
                );
            
            return response()->json([
                'success' => true,
                'message' => 'Installment paid successfully',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Reads documents[{correlationKey}][{index}][file|title|access] out
     * of the raw request into the plain array shape the services expect.
     * `$correlationKey` is `requestItemId` for POST, `reference` for PATCH.
     */
    private function extractDocuments(Request $request, string $correlationKey): array
    {
        $meta = (array) $request->input("documents.{$correlationKey}", []);
        $documents = [];

        foreach ($meta as $index => $docMeta) {
            $file = $request->file("documents.{$correlationKey}.{$index}.file");

            if (!$file) {
                continue;
            }

            $documents[] = [
                'file' => $file,
                'title' => $docMeta['title'] ?? null,
                'access' => $docMeta['access_level'] ?? null,
            ];
        }

        return $documents;
    }

    /**
     * Section 17 — shared 200/207/422 aggregation for both endpoints.
     */
    private function bulkResponse(array $results, string $successMessage, string $partialMessage)
    {
        $allSucceeded = collect($results)->every(fn ($r) => $r['result']['success'] === true);
        $allFailed = collect($results)->every(fn ($r) => $r['result']['success'] === false);

        $statusCode = match (true) {
            count($results) === 1 && $allSucceeded => 200,
            count($results) === 1 && $allFailed => 422,
            $allSucceeded, $allFailed => 207,
            default => 207,
        };

        return response()->json([
            'success' => $allSucceeded,
            'message' => $allSucceeded ? $successMessage : $partialMessage,
            'data' => $results,
        ], $statusCode);
    }
}