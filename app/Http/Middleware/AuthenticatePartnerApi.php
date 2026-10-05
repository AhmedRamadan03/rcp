<?php

namespace App\Http\Middleware;

use App\Enums\ApiPartnerStatus;
use App\Models\PartnerApiCredential;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

/**
 * 
 *
 * Flow (10.1), followed exactly in order:
 *   1. Receive X-Client-Id
 *   2. Find partner_api_credentials.api_client_id
 *   3. Verify credential is ACTIVE (not revoked, not soft-deleted)
 *   4. Verify X-Client-Secret against client_secret_hash
 *   5. Authorize API request
 *
 * Section 10.2 — every failure reason (id not found, wrong secret,
 * INACTIVE, revoked, soft-deleted) returns the SAME generic 401. We
 * never leak which specific check failed, so an attacker can't use the
 * response to enumerate valid api_client_id values.
 */
class AuthenticatePartnerApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiClientId = $request->header('X-Client-Id');
        $clientSecret = $request->header('X-Client-Secret');
        
        if (!$apiClientId || !$clientSecret) {
            return $this->unauthorized();
        }

        // Step 2: find by api_client_id. Eloquent's default query
        // automatically excludes soft-deleted rows, satisfying the
        // "not soft-deleted" part of step 3 for free.
        $credential = PartnerApiCredential::query()
            ->where('api_client_id', $apiClientId)
            ->first();

        if ($credential === null) {
            return $this->unauthorized();
        }

        // Step 3: must be ACTIVE (covers "not revoked" too, since
        // revoke() flips status to INACTIVE).
        if ($credential->status !== ApiPartnerStatus::ACTIVE->value) {
            return $this->unauthorized();
        }

        // Step 4: verify the secret against the stored hash.
        if (!Hash::check($clientSecret, $credential->client_secret_hash)) {
            return $this->unauthorized();
        }

        
        $request->attributes->set('partner_api_credential', $credential);
        // Step 6: authorize — request proceeds.
        return $next($request);
    }

    private function unauthorized(): Response
    {
        return response()->json([
            'success' => false,
            'statusCode' => 401,
            'message' => 'Unauthorized.',
        ], 401);
    }
}