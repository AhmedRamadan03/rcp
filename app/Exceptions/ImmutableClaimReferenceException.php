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
class ImmutableClaimReferenceException extends RuntimeException
{
    public function __construct(
        string $message = 'The RCP claim reference is immutable and cannot be changed after generation.'
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'statusCode' => 422,
            'message' => $this->getMessage(),
            'data' => null,
        ], 422);
    }
}