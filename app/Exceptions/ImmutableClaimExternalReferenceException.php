<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown by HasImmutableClaimReference when something tries to change
 * an already-generated Claim reference.
 *
 * Has its own render() so Laravel's exception handler returns it using
 * the common RCP API response envelope (Section 18) instead of falling
 * through to a raw 500. 422 is used per the API Error Codes table
 * (Section 19: "422 — Validation failure or unsupported enum / mapping
 * value") since this is fundamentally a validation-style rejection of
 * the submitted data, not a server fault.
 */
class ImmutableClaimExternalReferenceException extends RuntimeException
{
    public function __construct(
        public bool $isExists = true, 
       string $message = 'The RCP claim external reference already exists.'
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        if ($this->isExists) {
            return response()->json([
                'success' => false,
                'message' => $this->getMessage(),
            ], 409);
        }else{
            return response()->json([
                'success' => true,
                'message' => 'No duplicate claim found',
            ], 200);
            
        }
    }
}