<?php

namespace App\Imports;

use App\Models\Claim;
use App\Models\Assigning;
use App\Models\UploadedClaimsFile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;

class BulkAssignClaimsImport implements ToCollection, WithHeadingRow, WithStartRow
{
    use Importable, SkipsFailures;

    public array $assignments = [];
    public array $failedRows = [];
    
    // Cache for users to avoid repeated DB queries
    private array $userCache = [];
    
    // Cache for active assignments to avoid repeated DB queries
    private array $activeAssignmentsCache = [];

    public function __construct(public UploadedClaimsFile $claims_file) {}

    public function startRow(): int
    {
        return 2;
    }

    public function collection(Collection $rows)
    {
        // Extract and sanitize claim numbers
        $claimNumbers = $rows->pluck('claim_number')
            ->filter(fn($val) => $val !== null)
            ->map(fn($item) => trim((string)$item))
            ->values();

        $claims = Claim::whereIn('claim_number', $claimNumbers)->get();
        
        // Fetch all active assignments for the claims
        $claimIds = $claims->pluck('id')->toArray();
        $this->loadActiveAssignments($claimIds);

        $rowIndex = $this->startRow();

        foreach ($rows as $row) {
            // Extract data with named keys
            $claimNumber = isset($row['claim_number']) ? trim((string)$row['claim_number']) : null;
            $subClaimNumber = isset($row['sub_claim_number']) ? trim((string)$row['sub_claim_number']) : null;
            $toEmail = isset($row['to_email']) ? trim($row['to_email']) : null;
            $toName = isset($row['to_name']) ? trim($row['to_name']) : null;

            $failureReasons = [];

            // Validate required fields
            if (!$claimNumber) {
                $failureReasons[] = 'Claim number is required';
            }

            // Validate that at least one of to_email or to_name is provided
            if (!$toEmail && !$toName) {
                $failureReasons[] = 'Either to_email or to_name is required';
            }

            if (!empty($failureReasons)) {
                $this->failedRows[] = [
                    'claim_number' => $claimNumber,
                    'sub_claim_number' => $subClaimNumber,
                    'to_email' => $toEmail,
                    'to_name' => $toName,
                    'reasons' => $failureReasons,
                ];
                $rowIndex++;
                continue;
            }

            // Find the matching claim
            $claim = $this->findClaim($claims, $claimNumber, $subClaimNumber);

            if (!$claim) {
                $failureReasons[] = 'Claim not found';
            }

            // Find the user (prioritize email over name)
            $toUser = $this->findUser($toEmail, $toName);
            
            if (!$toUser) {
                if ($toEmail) {
                    $failureReasons[] = 'To user with email "' . $toEmail . '" not found';
                } else {
                    $failureReasons[] = 'To user with name "' . $toName . '" not found';
                }
            }

            // If any validation failed, add to failed rows
            if (!empty($failureReasons)) {
                $this->failedRows[] = [
                    'claim_number' => $claimNumber,
                    'sub_claim_number' => $subClaimNumber,
                    'to_email' => $toEmail,
                    'to_name' => $toName,
                    'reasons' => $failureReasons,
                ];
                $rowIndex++;
                continue;
            }

            // Find the active assignment for this claim to get the 'from' user
            $fromUserId = $this->getFromUserIdForClaim($claim->id);

            // All validations passed, create assignment
            $this->assignments[] = [
                'claim_id' => $claim->id,
                'supervisor_id' => auth()->id(),
                'to' => $toUser->id,
                'from' => $fromUserId,
                'created_at' => Carbon::now(),
                'assign_date' => Carbon::now(),
                'is_active' => 1,
            ];

            $rowIndex++;
        }
    }

    /**
     * Load all active assignments for given claim IDs into cache
     * 
     * @param array $claimIds
     * @return void
     */
    private function loadActiveAssignments(array $claimIds): void
    {
        if (empty($claimIds)) {
            return;
        }

        $activeAssignments = Assigning::whereIn('claim_id', $claimIds)
            ->where('is_active', 1)
            ->get();

        foreach ($activeAssignments as $assignment) {
            $this->activeAssignmentsCache[$assignment->claim_id] = $assignment;
        }
    }


    private function getFromUserIdForClaim(int $claimId)
    {
        // Check if there's an active assignment for this claim
        if (isset($this->activeAssignmentsCache[$claimId])) {
            // Use the 'to' user of the active assignment as 'from' for the new assignment
            return $this->activeAssignmentsCache[$claimId]->to;
        }

        return null;
    }

    /**
     * Find a user by email or name with caching
     * Prioritizes email over name if both are provided
     * 
     * @param string|null $email
     * @param string|null $name
     * @return User|null
     */
    private function findUser(?string $email, ?string $name): ?User
    {
        // If email is provided, search by email first
        if ($email) {
            return $this->findUserByEmail($email);
        }
        
        // If no email but name is provided, search by name
        if ($name) {
            return $this->findUserByName($name);
        }
        
        return null;
    }

    /**
     * Find a user by email with caching
     * 
     * @param string $email
     * @return User|null
     */
    private function findUserByEmail(string $email): ?User
    {
        $cacheKey = 'email:' . strtolower($email);
        
        if (isset($this->userCache[$cacheKey])) {
            return $this->userCache[$cacheKey];
        }
        
        $user = User::where('email', $email)
            ->whereNull('deleted_at')
            ->first();
        
        $this->userCache[$cacheKey] = $user;
        
        return $user;
    }

    /**
     * Find a user by name (case-insensitive) with caching
     * 
     * @param string $name
     * @return User|null
     */
    private function findUserByName(string $name): ?User
    {
        $normalizedName = strtolower($name);
        $cacheKey = 'name:' . $normalizedName;
        
        if (isset($this->userCache[$cacheKey])) {
            return $this->userCache[$cacheKey];
        }
        
        $user = User::whereRaw('LOWER(name) = ?', [$normalizedName])
            ->whereNull('deleted_at')
            ->first();
        
        $this->userCache[$cacheKey] = $user;
        
        return $user;
    }

    /**
     * Find a claim by claim_number and sub_claim_number
     * 
     * @param Collection $claims
     * @param string $claimNumber
     * @param string|null $subClaimNumber
     * @return Claim|null
     */
    private function findClaim(Collection $claims, string $claimNumber, ?string $subClaimNumber): ?Claim
    {
        return $claims->first(function (Claim $claim) use ($claimNumber, $subClaimNumber) {
            $claimMatches = $claim->claim_number === $claimNumber;

            // If no sub_claim_number provided in the file, match by claim_number only
            if ($subClaimNumber === null || $subClaimNumber === '') {
                return $claimMatches;
            }

            // If sub_claim_number provided, exact match required
            return $claimMatches && (string) $claim->sub_claim_number === $subClaimNumber;
        });
    }

    /**
     * Get summary of import results
     *
     * @return array
     */
    public function getSummary(): array
    {
        return [
            'total_rows' => count($this->assignments) + count($this->failedRows),
            'successful_assignments' => count($this->assignments),
            'failed_rows' => count($this->failedRows),
        ];
    }
}