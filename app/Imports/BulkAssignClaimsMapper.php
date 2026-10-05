<?php

namespace App\Imports;

use App\Models\Assigning;
use App\Models\Claim;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Stateless row mapper for bulk claim assignment imports.
 *
 * Mirrors the role ClaimImportWithActionMapper plays for the backlog import:
 * it owns all validation, entity resolution, and assignment-data building
 * logic, keeping the job itself thin.
 *
 * Caches are passed in by reference from the job so they persist across rows
 * without this class needing to hold state.
 */
class BulkAssignClaimsMapper
{
    /**
     * Validate a row and build an assignment array ready for Assigning::insert().
     *
     * @param array       $rowData        Snake-cased row from OpenSpout
     * @param int         $supervisorId   ID of the user who dispatched the job
     * @param array       &$userCache     Persistent user resolution cache
     * @param array       &$claimCache    Persistent claim resolution cache
     * @param array       &$assignCache   Persistent active-assignment cache
     * @return array{assignment: array}
     * @throws \Exception Descriptive message(s) joined by '; '
     */
    public function mapToAssignment(
        array $rowData,
        int $supervisorId,
        array &$userCache,
        array &$claimCache,
        array &$assignCache,
    ): array {
        $claimNumber    = isset($rowData['claim_number'])     ? trim((string) $rowData['claim_number'])     : null;
        $subClaimNumber = isset($rowData['sub_claim_number']) ? trim((string) $rowData['sub_claim_number']) : null;
        $toEmail        = isset($rowData['to_email'])         ? trim((string) $rowData['to_email'])         : null;
        $toName         = isset($rowData['to_name'])          ? trim((string) $rowData['to_name'])          : null;

        // Validation 
        $this->validate($rowData, $claimNumber, $toEmail, $toName);

        $claim = $this->resolveClaim($claimNumber, $subClaimNumber, $claimCache);

        if (!$claim) {
            throw new \Exception('Claim not found');
        }

        $toUser = $this->resolveUser($toEmail, $toName, $userCache);

        if (!$toUser) {
            $label = $toEmail ? "email '{$toEmail}'" : "name '{$toName}'";
            throw new \Exception("User with {$label} not found");
        }

        $fromUserId = $this->resolveFromUser($claim->id, $assignCache);

        return [
            'assignment' => [
                'claim_id'      => $claim->id,
                'supervisor_id' => $supervisorId,
                'to'            => $toUser->id,
                'from'          => $fromUserId,
                'created_at'    => now(),
                'assign_date'   => now(),
                'is_active'     => 1,
            ],
        ];
    }

    // Validation 

    /**
     * @throws \Exception
     */
    protected function validate(array $row, ?string $claimNumber, ?string $toEmail, ?string $toName): void
    {
        $errors = [];

        if (!$claimNumber) {
            $errors[] = 'Claim number is required';
        }

        if (!$toEmail && !$toName) {
            $errors[] = 'Either to_email or to_name is required';
        }

        if ($toEmail && !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "The email '{$toEmail}' is not a valid email address";
        }

        if (!empty($errors)) {
            throw new \Exception(implode('; ', $errors));
        }
    }

    // ── Entity resolution (cached) ────────────────────────────────────────────

    /**
     * Load all sub-claims for claim_number on first encounter, then match
     * by sub_claim_number if provided — identical logic to BulkAssignClaimsImport::findClaim().
     */
    protected function resolveClaim(string $claimNumber, ?string $subClaimNumber, array &$cache): ?Claim
    {
        if (!isset($cache[$claimNumber])) {
            $cache[$claimNumber] = Claim::where('claim_number', $claimNumber)->get();
        }

        /** @var Collection $claims */
        $claims = $cache[$claimNumber];

        return $claims->first(function (Claim $claim) use ($claimNumber, $subClaimNumber) {
            $claimMatches = $claim->claim_number === $claimNumber;

            if ($subClaimNumber === null || $subClaimNumber === '') {
                return $claimMatches;
            }

            return $claimMatches && (string) $claim->sub_claim_number === $subClaimNumber;
        });
    }

    /**
     * Resolve a User by email (preferred) or name with a shared cache.
     */
    protected function resolveUser(?string $email, ?string $name, array &$cache): ?User
    {
        if ($email) {
            $key = 'email:' . strtolower($email);
            if (!array_key_exists($key, $cache)) {
                $cache[$key] = User::where('email', $email)->whereNull('deleted_at')->first();
            }
            return $cache[$key];
        }

        if ($name) {
            $key = 'name:' . strtolower($name);
            if (!array_key_exists($key, $cache)) {
                $cache[$key] = User::whereRaw('LOWER(name) = ?', [strtolower($name)])->whereNull('deleted_at')->first();
            }
            return $cache[$key];
        }

        return null;
    }

    /**
     * Return the 'to' user of the currently active assignment so it becomes
     * the 'from' of the new one. Result is cached to avoid repeated queries.
     */
    protected function resolveFromUser(int $claimId, array &$cache): ?int
    {
        if (!array_key_exists($claimId, $cache)) {
            $cache[$claimId] = Assigning::where('claim_id', $claimId)
                ->where('is_active', 1)
                ->value('to');
        }

        return $cache[$claimId];
    }
}