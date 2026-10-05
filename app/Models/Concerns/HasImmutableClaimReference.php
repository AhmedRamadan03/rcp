<?php

namespace App\Models\Concerns;

use App\Exceptions\ImmutableClaimReferenceException;

/**
 * Guarantees that Claim::reference can only be set ONCE (on creation)
 * and can never be changed afterwards — including via mass assignment,
 * PATCH /claims, or the RCP UI.
 *
 * Add to Claim model:
 *   use HasImmutableClaimReference;
 *
 * The internal backfill command bypasses this on purpose by writing
 * directly via the query builder (DB::table('claims')->update(...))
 * for the legitimate first-time assignment, so this guard only ever
 * fires on a genuine attempt to CHANGE an existing reference.
 *
 * Throws ImmutableClaimReferenceException (not a plain RuntimeException)
 * so the API layer renders a proper 422 JSON error instead of a raw 500
 * — see app/Exceptions/ImmutableClaimReferenceException.php.
 */
trait HasImmutableClaimReference
{
    public static function bootHasImmutableClaimReference(): void
    {
        static::updating(function ($model) {
            if ($model->isDirty('reference') && $model->getOriginal('reference') !== null) {
                throw new ImmutableClaimReferenceException();
            }
        });
    }
}