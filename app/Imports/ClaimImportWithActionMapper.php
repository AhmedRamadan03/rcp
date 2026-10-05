<?php

namespace App\Imports;

use App\Enums\ClaimType;
use App\Enums\DebtorType;
use App\Enums\ReturnReason;
use App\Enums\Roles;
use App\Models\City;
use App\Models\Claim;
use App\Models\User;
use App\Models\UploadedClaimsFile;
use App\Enums\ClaimStatus;
use Carbon\Carbon;
use Illuminate\Support\Str;


class ClaimImportWithActionMapper
{
    /**
     * Create a new mapper instance.
     *
     * @param User $user The user who initiated the import (uploader)
     * @param UploadedClaimsFile $uploadedFile The uploaded claims file being processed
     */
    public function __construct(
        public User $user,
        public UploadedClaimsFile $uploadedFile
    ) {
    }

    /**
     * Map Excel row data to a Claim model instance.
     *
     * This is the main entry point for the mapper. It validates the row data,
     * transforms it according to business rules, and creates a Claim instance.
     *
     * @param array $row Row data with column headers as keys (snake_case)
     * @return array The created claim instance
     * @throws \Exception If validation fails or required data is invalid
     */
    public function mapToClaim(array $row)
    {
        // Validate row data first
        $this->validate($row);

        // Parse mobile number
        $party_mobile_number = isset($row['debtor_phone_number']) && $row['debtor_phone_number'] !== '' ? $this->parseMobileNumber($row['debtor_phone_number']) : null;

        // Parse dates from Excel format supports Excel serial numbers, d/m/Y strings, or any strtotime-compatible format
        $intimation_date = $this->parseDateToYmd($row['intimation_date'] ?? null);
        $accident_date = $this->parseDateToYmd($row['accident_date']);
        $date_to_delay = $this->parseDateToYmd($row['date_to_delay'] ?? null);
        $installment_date = $this->parseDateToYmd($row['installment_date'] ?? null);

        $subClaimNumber = $subClaimNumber = (isset($row['sub_claim_number']) && $row['sub_claim_number'] !== '')
            ? $row['sub_claim_number']
            : null;

        // Build claim data array
        $claimData = [
            'client_id' => $this->user->client_id,
            'file_id' => $this->uploadedFile->id,
            'insertion_type' => \App\Enums\ClaimInsertionType::BACKLOG,
            'policy_number' => $row['policy_number'],
            'claim_number' => $row['claim_number'],
            'sub_claim_number' => $subClaimNumber,
            'amount' => $row['claim_amount'],
            'type' => (ClaimType::tryFrom(strtolower(trim($row['claim_type'] ?? ''))) ?? ClaimType::INDIVIDUAL)->value,
            'debtor_name' => $row['debtor_name'],
            'debtor_type' => (DebtorType::tryFrom(strtolower(trim($row['debtor_type'] ?? ''))) ?? DebtorType::THIRD_PARTY)->value,
            'debtor_nid' => $row['debtor_id'],
            'debtor_phone' => $party_mobile_number,
            'plate_number' => $row['plate_number'] ?? '',
            'victim_plate_number' => $row['victim_plate_number'] ?? '',
            'accident_number' => $row['accident_number'] ?? '',
            'intimation_date' => $intimation_date,
            'accident_date' => $accident_date,
            'date_to_delay' => $date_to_delay,
            'installment_date' => $installment_date,
            'is_draft' => true,
            'car_type' => isset($row['vehicle_make'], $row['vehicle_model'])
                ? $row['vehicle_make'] . ' - ' . $row['vehicle_model']
                : ($row['vehicle_make'] ?? $row['vehicle_model'] ?? null),
            'mistake_percentage' => isset($row['mistake_percentage']) && $row['mistake_percentage'] !== ''
                ? (int) $row['mistake_percentage']
                : null,
            'remark' => $row['remarks'] ?? '',
            'return_reason' => isset($row['recovery_reason'])
                ? $this->getReturnReasonId($row['recovery_reason'])
                : null,
            'is_backlog' => true,
            'is_msg_skip' => true,
        ];

        // Set collector/user
        $collector = $collector = $this->getCollector(
            $row['employee_email'] ?? null,
            $row['employee_name'] ?? null,
        );

        $claimData['user_id'] = $this->user->id;
        // Handle city lookup/creation
        $claimData['city_id'] = null;
        // Trim and check if city has actual content
        if (!empty(trim($row['city'] ?? ''))) {
            $cityName = Str::lower(trim($row['city']));

            $city = City::where('name', $cityName)->first();

            if ($city) {
                $claimData['city_id'] = $city->id;
            } else {
                try {
                    $city = City::create(['name' => $cityName]);
                    $claimData['city_id'] = $city->id;
                } catch (\Exception $e) {
                    \Log::warning("Failed to create city '{$cityName}': " . $e->getMessage());
                }
            }
        }

        // Trim string fields
        $claimData['claim_number'] = Str::of($claimData['claim_number'])->trim();
        $claimData['debtor_phone'] = Str::of($claimData['debtor_phone'])->trim();
        $claimData['debtor_nid'] = Str::of($claimData['debtor_nid'])->trim();
        $claimData['sub_claim_number'] = $subClaimNumber ? Str::of($subClaimNumber)->trim() : null;
        $claimData['amount'] = $this->parseNumericAmount($claimData['amount'], 'amount');
        $claimData['recovered_amount'] = $this->parseNumericAmount($claimData['recovered_amount'] ?? null, 'recovered_amount');
        // Create the claim
        $claim = Claim::create($claimData);

        // Create assignment for the claim
        if ($collector != null) {
            $this->createAssignment($claim, $collector);
        }

        return [
            'claim' => $claim,
            'data' => [
                'formatted_date_to_delay' => $date_to_delay,
                'formatted_installment_date' => $installment_date,
            ]

        ];
    }



    /**
     * Validate row data before processing.
     *
     * Checks for:
     * - Required fields (claim_number, claim_amount)
     * - Duplicate claim detection using ClaimService
     * - Employee email validation
     *
     * @param array $row Row data to validate
     * @return void
     * @throws \Exception If validation fails with descriptive error message
     */
    protected function validate(array $row): void
    {
        $errors = [];

        // Required fields
        if (!isset($row['claim_number']) || empty($row['claim_number'])) {
            $errors[] = "Required field 'claim_number' is missing";
        }

        if (!isset($row['claim_amount']) || $row['claim_amount'] === '' || $row['claim_amount'] === null) {
            $errors[] = "Required field 'claim_amount' is missing";
        }

        if (!isset($row['policy_number']) || $row['policy_number'] === '' || $row['policy_number'] === null) {
            $errors[] = "Required field 'policy_number' is missing";
        }

        if (!isset($row['debtor_name']) || $row['debtor_name'] === '' || $row['debtor_name'] === null) {
            $errors[] = "Required field 'debtor_name' is missing";
        }

        if (!isset($row['debtor_id']) || $row['debtor_id'] === '' || $row['debtor_id'] === null) {
            $errors[] = "Required field 'debtor_id' is missing";
        }

        if (isset($row['claim_type']) && $row['claim_type'] !== '' && $row['claim_type'] !== null) {
            $normalized = strtolower(trim($row['claim_type']));

            if (!ClaimType::tryFrom($normalized)) {
                $errors[] = "Invalid 'claim_type' value '{$row['claim_type']}'. Must be a valid claim type.";
            }
        }

        // accident_date must be present and valid before we can compare intimation_date against it
        $accidentDateYmd = null;

        if (!isset($row['accident_date']) || $row['accident_date'] === '' || $row['accident_date'] === null) {
            $errors[] = "Required field 'accident_date' is missing";
        } else {
            try {
                $accidentDateYmd = $this->parseDateToYmd($row['accident_date']);
            } catch (\Exception $e) {
                $errors[] = "The 'accident_date' value '{$row['accident_date']}' is not a valid date. Expected a date (e.g. 28/02/2026).";
            }
        }

        if (!empty($row['intimation_date'])) {
            try {
                $intimationDateYmd = $this->parseDateToYmd($row['intimation_date']);

                // Only compare once accident_date itself parsed successfully;
                // if it didn't, its own error above already covers the row.
                if ($accidentDateYmd !== null && $intimationDateYmd < $accidentDateYmd) {
                    $errors[] = "The 'intimation_date' value '{$row['intimation_date']}' must be on or after the 'accident_date' value '{$row['accident_date']}'.";
                }
            } catch (\Exception $e) {
                $errors[] = "The 'intimation_date' value '{$row['intimation_date']}' is not a valid date. Expected a date (e.g. 28/02/2026).";
            }
        }

        // Phone number validation
        if (isset($row['debtor_phone_number']) && $row['debtor_phone_number'] !== '' && $row['debtor_phone_number'] !== null) {
            $rawPhone = trim((string) $row['debtor_phone_number']);
            try {
                $phone = new \Propaganistas\LaravelPhone\PhoneNumber($rawPhone, 'SA');
                if (!$phone->isValid()) {
                    $errors[] = "The 'debtor_phone_number' value '{$row['debtor_phone_number']}' is not a valid phone number.";
                }
            } catch (\Throwable $e) {
                $errors[] = "The 'debtor_phone_number' value '{$row['debtor_phone_number']}' is not a valid phone number.";
            }
        }

        // Duplicate claim detection
        if (isset($row['claim_number']) && !empty($row['claim_number'])) {
            $claimNumber = $row['claim_number'];
            $debtorNid = $row['debtor_id'];
            $amount = (float) $row['claim_amount'];
            $subClaimNumber = isset($row['sub_claim_number']) && $row['sub_claim_number'] !== ''
                ? $row['sub_claim_number']
                : null;

            $isDuplicate = \App\Services\ClaimService::isDuplicateClaim(
                $claimNumber,
                $debtorNid,
                $amount,
                $subClaimNumber
            )->exists();

            if ($isDuplicate) {
                $errors[] = "Duplicate claim detected: claim_number={$claimNumber}, " .
                    "debtor_nid={$debtorNid}, amount={$amount}, " .
                    "sub_claim_number=" . ($subClaimNumber ?? 'N/A');
            }
        }

        // Numeric field validation
        if (isset($row['claim_amount']) && $row['claim_amount'] !== '' && $row['claim_amount'] !== null) {
            try {
                $this->parseNumericAmount($row['claim_amount'], 'claim_amount');
            } catch (\InvalidArgumentException $e) {
                $errors[] = $e->getMessage() . " in claim {$row['claim_number']}";
            }
        }

        if (isset($row['recovered_amount']) && $row['recovered_amount'] !== '' && $row['recovered_amount'] !== null) {
            try {
                $this->parseNumericAmount($row['recovered_amount'], 'recovered_amount');
            } catch (\InvalidArgumentException $e) {
                $errors[] = $e->getMessage() . " in claim {$row['claim_number']}";
            }

            if (
                $this->parseNumericAmount($row['recovered_amount'], 'recovered_amount') >
                $this->parseNumericAmount($row['claim_amount'], 'claim_amount')
            ) {
                $errors[] = "The 'recovered_amount' cannot be greater than 'claim_amount' in claim {$row['claim_number']}";
            }
        }

        // Employee email validation
        if (isset($row['employee_email']) && is_string($row['employee_email']) && !empty($row['employee_email'])) {
            $email = $row['employee_email'];

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "The employee email '{$email}' is not a valid email address";
            } else {
                $userExists = User::where('email', $email)->first();
                if (!$userExists) {
                    $errors[] = "The employee email '{$email}' does not exist in the system";
                } else {
                    if (isset($row['claim_status']) && $row['claim_status'] === ClaimStatus::LAW_FIRM->value) {
                        if ($userExists->role_id !== Roles::LEGAL_DEPARTMENT->value) {
                            $errors[] = "The employee '{$email}' does not have the role of legal department in the system";
                        }
                    }
                }
            }
        }

        if (
            (empty($row['employee_email'])) &&
            isset($row['employee_name']) && is_string($row['employee_name']) && !empty($row['employee_name'])
        ) {
            $name = $row['employee_name'];
            $userExists = User::whereRaw('LOWER(name) = ?', [strtolower($name)])->exists();
            if (!$userExists) {
                $errors[] = "The employee name '{$name}' does not exist in the system";
            }
        }

        // Debtor type insured validation
        if (isset($row['debtor_type']) && $row['debtor_type'] === DebtorType::INSURED->value) {
            if (!isset($row['recovery_reason']) || empty($row['recovery_reason'])) {
                $errors[] = "Required field 'recovery_reason' is missing";
            } else {
                if ($this->getReturnReasonId($row['recovery_reason']) === null) {
                    $errors[] = "Invalid 'recovery_reason' value '{$row['recovery_reason']}'. Must be a valid return reason.";
                }
            }
        }

        // Closed status validation
        if (isset($row['claim_status']) && $row['claim_status'] === ClaimStatus::CLOSED->value) {
            if (!isset($row['close_reason']) || empty($row['close_reason'])) {
                $errors[] = "Required field 'close_reason' is missing";
            }
        }

        // Mistake percentage validation
        if (isset($row['mistake_percentage']) && $row['mistake_percentage'] !== '' && $row['mistake_percentage'] !== null) {
            if (
                filter_var($row['mistake_percentage'], FILTER_VALIDATE_INT) === false ||
                (int) $row['mistake_percentage'] < 1 ||
                (int) $row['mistake_percentage'] > 100
            ) {
                $errors[] = "Invalid mistake_percentage value '{$row['mistake_percentage']}'. Must be an integer between 1 and 100";
            }
        }

        // Delay status validation
        if (isset($row['claim_status']) && $row['claim_status'] === ClaimStatus::DELAY->value) {
            if (!isset($row['date_to_delay']) || empty($row['date_to_delay'])) {
                $errors[] = "Required field 'date_to_delay' is missing for 'follow_up/delay' status";
            } else {
                try {
                    $dateToDelay = Carbon::createFromFormat('Y-m-d', $this->parseDateToYmd($row['date_to_delay']))->startOfDay();
                    if (!$dateToDelay->isFuture()) {
                        $errors[] = "The 'date_to_delay' must be a future date for 'follow_up/delay' status";
                    }
                } catch (\Exception $e) {
                    $errors[] = "The 'date_to_delay' value '{$row['date_to_delay']}' is not a valid date. Expected a date (e.g. 2026/02/28).";
                }
            }

            if (isset($row['recovered_amount']) && isset($row['claim_amount']) && $row['recovered_amount'] !== '' && $row['claim_amount'] !== '') {
                try {
                    $recoveredAmount = $this->parseNumericAmount($row['recovered_amount'], 'recovered_amount');
                    $claimAmount = $this->parseNumericAmount($row['claim_amount'], 'claim_amount');

                    if ($recoveredAmount === $claimAmount) {
                        $errors[] = "The 'recovered_amount' cannot be equal to 'claim_amount' for delayed claims in claim {$row['claim_number']}";
                    }
                } catch (\InvalidArgumentException $e) {
                }
            }

        }

        // Partial status validation
        if (isset($row['claim_status']) && $row['claim_status'] === ClaimStatus::PARTIAL->value) {
            if (!isset($row['installment_date']) || empty($row['installment_date'])) {
                $errors[] = "Required field 'installment_date' is missing for 'follow_up/partial' status";
            } else {
                try {
                    $installmentDate = $this->parseDateToYmd($row['installment_date']);
                    if (
                        Carbon::createFromFormat('Y-m-d', $installmentDate)
                            ->startOfDay()
                            ->lessThan(Carbon::today())
                    ) {
                        $errors[] = "The 'installment_date' must be a future date for 'follow_up/partial' status";
                    }
                } catch (\Exception $e) {
                    $errors[] = "The 'installment_date' value '{$row['installment_date']}' is not a valid date. Expected a date (e.g. 2026/02/28).";
                }
            }
            if (isset($row['recovered_amount']) && isset($row['claim_amount']) && $row['recovered_amount'] !== '' && $row['claim_amount'] !== '') {
                try {
                    $recoveredAmount = $this->parseNumericAmount($row['recovered_amount'], 'recovered_amount');
                    $claimAmount = $this->parseNumericAmount($row['claim_amount'], 'claim_amount');

                    if ($recoveredAmount === $claimAmount) {
                        $errors[] = "The 'recovered_amount' cannot be equal to 'claim_amount' for partial claims in claim {$row['claim_number']}. Please add the claim to the manual collect template instead.";
                    }
                } catch (\InvalidArgumentException $e) {
                }
            }
        }

        if (!empty($errors)) {
            throw new \Exception(implode('; ', $errors));
        }
    }

    /**
     * Parse and validate a numeric amount field.
     *
     * Trims whitespace, validates numeric format, and casts to float.
     * Ensures compatibility with double(8, 2) database columns.
     *
     * @param mixed $value The raw value from Excel
     * @param string $fieldName The field name for error reporting
     * @return float The parsed numeric value
     * @throws \InvalidArgumentException If value is not numeric or exceeds precision
     */
    protected function parseNumericAmount($value, string $fieldName): float
    {
        // Convert to string and trim whitespace
        $trimmedValue = is_string($value) ? trim($value) : (string) $value;
        $trimmedValue = trim($trimmedValue);

        // Handle null or empty values
        if ($value === null || $value === '') {
            // recovered_amount can be null/empty and defaults to 0.00
            if ($fieldName === 'recovered_amount') {
                return 0.00;
            }
            // Other fields (like amount) are required
            throw new \InvalidArgumentException(
                "Invalid numeric value for {$fieldName}: field is empty or null"
            );
        }

        // Check for whitespace-only input
        if ($trimmedValue === '') {
            throw new \InvalidArgumentException(
                "Invalid numeric value for {$fieldName}: field contains only whitespace"
            );
        }

        // Validate numeric format
        if (!is_numeric($trimmedValue)) {
            throw new \InvalidArgumentException(
                "Invalid numeric value for {$fieldName}: '{$trimmedValue}' is not a valid number"
            );
        }

        // Cast to float
        $floatValue = (float) $trimmedValue;

        // Validate precision: max 8 digits total, 2 decimal places
        // This means max value is 999999.99
        $maxValue = 999999.99;
        if (abs($floatValue) > $maxValue) {
            throw new \InvalidArgumentException(
                "Amount value {$floatValue} exceeds maximum precision of 8 digits (2 decimal) for {$fieldName}"
            );
        }

        // Round to 2 decimal places using standard financial rounding (round half up)
        $roundedValue = round($floatValue, 2, PHP_ROUND_HALF_UP);

        return $roundedValue;
    }

    // protected function calculateLiability(string $debtorType, int $mistake_percentage): int
    // {
    //     return $debtorType == DebtorType::INSURED->value ? $mistake_percentage : 100 - $mistake_percentage;
    // }


    protected function getCollector(?string $email = null, ?string $name = null): ?User
    {
        if (!empty($email)) {
            $user = User::where('email', $email)->first();
            if ($user) {
                return $user;
            }
        }

        if (!empty($name)) {
            $user = User::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
            if ($user) {
                return $user;
            }
        }

        return null;
    }


    protected function parseMobileNumber($mobileNumber): string
    {
        $raw = trim((string) $mobileNumber);

        try {
            $phone = new \Propaganistas\LaravelPhone\PhoneNumber($raw, 'SA');

            if ($phone->getCountry() === 'SA') {
                return $phone->formatE164();
            }
            return $raw;
        } catch (\Throwable) {
            return $raw;
        }
    }



    protected function parseDateToYmd(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
            if (!$date) {
                throw new \Exception("Invalid Excel serial date value: '{$value}'.");
            }
            return $date->format('Y-m-d');
        }

        $trimmed = trim(str_replace('\/', '/', (string) $value));

        // Try strict d/m/Y first
        $date = \DateTime::createFromFormat('d/m/Y', $trimmed);
        if ($date && $date->format('d/m/Y') === $trimmed) {
            return $date->format('Y-m-d');
        }

        // Try Y-m-d
        $date = \DateTime::createFromFormat('Y-m-d', $trimmed);
        if ($date && $date->format('Y-m-d') === $trimmed) {
            return $date->format('Y-m-d');
        }

        throw new \Exception(
            "Unable to parse date value: '{$value}'. Expected format: Y/m/d (e.g. 2026/03/17)."
        );
    }

    protected function createAssignment(Claim $claim, $collector): void
    {

        \App\Models\Assigning::create([
            'claim_id' => $claim->id,
            'to' => $collector->id,
            'supervisor_id' => $this->user->id ?? null,
            'is_active' => 1,
            'assign_date' => now(),
        ]);
    }

    protected function getReturnReasonId(string $value): ?int
    {
        $normalizedInput = $this->normalizeArabicText($value);

        foreach (ReturnReason::cases() as $case) {
            // Check both Arabic and English translations
            $arabicString = ReturnReason::getString($case->value, 'ar');
            $englishString = ReturnReason::getString($case->value, 'en');

            // Exact match first (fast path)
            if ($arabicString === $value || $englishString === $value) {
                return $case->value;
            }

            // Normalized Arabic match
            if ($arabicString && $this->normalizeArabicText($arabicString) === $normalizedInput) {
                return $case->value;
            }

            // English case-insensitive match
            if ($englishString && strcasecmp($englishString, $value) === 0) {
                return $case->value;
            }

            // Partial containment match for Arabic (handles shortened versions)
            if ($arabicString && $normalizedInput) {
                $normalizedDb = $this->normalizeArabicText($arabicString);

                // If input is contained in DB or vice versa
                if (mb_strpos($normalizedDb, $normalizedInput) !== false ||
                    mb_strpos($normalizedInput, $normalizedDb) !== false) {
                    return $case->value;
                }
            }
        }

        return null;
    }

    protected function normalizeArabicText(string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // Remove Arabic diacritics (tashkeel)
        $text = preg_replace('/[\x{064B}-\x{065F}]/u', '', $text);

        // Normalize different forms of Alef
        $text = str_replace(['أ', 'إ', 'آ'], 'ا', $text);

        // Normalize Taa Marbuta and Ha
        $text = str_replace('ة', 'ه', $text);

        // Normalize Hamza variations
        $text = str_replace(['ؤ', 'ئ'], ['و', 'ي'], $text);

        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', trim($text));

        // Convert to lowercase (Arabic-aware)
        $text = mb_strtolower($text, 'UTF-8');

        return $text;
    }

}
