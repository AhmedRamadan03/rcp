<?php

namespace App\Http\Controllers\Api\V1\Partner;

use App\Enums\ApiPartnerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Settings\GeneratePartnerApiRequest;
use App\Http\Resources\Api\V1\Collections\Collection;
use App\Http\Resources\Api\V1\PartnerApiResource;
use App\Models\PartnerApiCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PartnerApiCredentialController extends Controller
{
    /**
     * GET /partner-api-credentials
     * Lists all credentials for the authenticated user's client —
     * including multiple ACTIVE ones side by side (4.2).
     */
    public function allApiCredential(Request $request)
    {
        $perPage = $request->integer('per_page', 10);
        $filters = request()->only(['search', 'status','api_client_id']);

        $credentials = PartnerApiCredential::with('createdBy','revokedBy')->filter($filters)->orderByDesc('created_at')->paginate($perPage);

        return response()->json(
            new Collection($credentials, 'partner api credentials information', PartnerApiResource::class)
        );
    }

    /**
     * POST /settings/generate-api-credential
     *
     * 4.2 constraints applied here:
     *   this up regardless; generated with enough entropy that a
     *   collision is not a realistic concern).
     * - Does NOT deactivate or touch any existing credential for this
     *   client — a client may have several ACTIVE credentials at once.
     * - No expires_at is set or supported — credentials never expire
     *   automatically.
     */
    public function generateApiCredential(GeneratePartnerApiRequest $request)
    {
        $data = $request->validated();

        $user = auth()->user();
        $apiClientId = 'rcp_client_' . Str::random(64);
        $clientSecret = Str::random(64);

        $credential = PartnerApiCredential::create([
            'title' => $data['title'],
            'api_client_id' => $apiClientId,
            'client_id'=> $user->client_id,
            'client_secret_hash' => Hash::make($clientSecret),
            'status' => ApiPartnerStatus::ACTIVE->value,
            'created_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Credential generated. Store the client secret now — it will not be shown again.',
            'data' => [
                'api_client_id' => $credential->api_client_id,
                'client_secret' => $clientSecret, // 5.1: shown once only, never persisted raw
                
            ],
        ]);
    }

    /**
     * DELETE /settings/delete-api-credential/{partnerApiCredential}
     *
     * 4.2: deletion must be soft — deleted_at is set, the row (and any
     * audit trail referencing it) is retained.
     */
    public function deleteApiCredential(PartnerApiCredential $partnerApiCredential)
    {
        $partnerApiCredential->delete(); // soft delete via SoftDeletes trait

        return response()->json([
            'success' => true,
            'message' => 'Credential deleted successed .',
        ]);
    }
   /**
     * POST /settings/revoke-api-credentials/{partnerApiCredential}
     *
     * Section 6 (Credential Lifecycle): ACTIVE -> INACTIVE / REVOKED.
     * Once revoked, the credential must fail authentication (10.2)
     * even though the row itself still exists (only soft-delete removes
     * the row from listings — revoke just disables it).
     *
     * This is DIFFERENT from destroy(): revoke keeps the row fully
     * visible/listed (status flips to INACTIVE + revoked_at/revoked_by
     * are stamped), destroy soft-deletes it entirely.
     */
    public function revokeApiCredential(PartnerApiCredential $partnerApiCredential)
    {
        $user = auth()->user();
 
        $partnerApiCredential->update([
            'status' => ApiPartnerStatus::INACTIVE->value,
            'revoked_by' => $user->id,
            'revoked_at' => now(),
        ]);
 
        return response()->json([
            'success' => true,
            'message' => 'Credential revoked successfully',
        ]);
    }
}