<?php

namespace App\Imports\ART;

use App\Enums\ClaimInsertionType;
use App\Enums\ClaimType;
use App\Enums\DebtorType;
use App\Enums\NotificationType;
use App\Enums\ReturnReason;
use App\Enums\Roles;
use App\Models\City;
use App\Models\Claim;
use App\Models\UploadedClaimsFile;
use App\Models\User;
use App\Services\AssigningService;
use App\Services\ClaimService;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Concerns\RemembersRowNumber;
use Illuminate\Support\Facades\Log;


class ArtClaimImport implements SkipsOnFailure, ToModel, WithHeadingRow, WithStartRow, WithValidation, WithEvents, WithChunkReading
{
    use Importable, RegistersEventListeners, SkipsFailures, RemembersRowNumber;

    //    public $cities;

    public $collectors;

    protected $claimService;

    private $currentRow = [];

    /**
     * Flag to indicate if the current row should be skipped
     */
    private bool $shouldSkipRow = false;

    /**
     * Tracks claim => collectorId for claims where a real employee was matched.
     * Used in afterImport to create assignings without re-reading the file.
     */
    public static array $claimAssignments = [];

    public function __construct(public User $user, public UploadedClaimsFile $claims_file)
    {
        self::$claimAssignments = [];
        $this->collectors = User::select('id', 'name', 'email','supervisor_id')->get();
        $this->claimService = new ClaimService;
    }

    public function prepareForValidation($data, $index)
    {
        // Reset flag for each row
        $this->shouldSkipRow = false;

        // Check if this is an empty row
        $this->shouldSkipRow = $this->isEmptyRow($data);

        // Excel returns 0 for empty numeric cells — treat as null so nullable rules skip them
        if (isset($data['mistake_percentage']) && $data['mistake_percentage'] === 0) {
            $data['mistake_percentage'] = null;
        }

        // Trim employee fields
        if (isset($data['employee_name']) && is_string($data['employee_name'])) {
            $data['employee_name'] = trim($data['employee_name']);
        }

        if (isset($data['employee_email']) && is_string($data['employee_email'])) {
            $data['employee_email'] = trim($data['employee_email']);
        }

        // Preserve current row for duplicate checking
        $this->currentRow = $data;

        return $data;
    }

    public function rules(): array
    {
        // Skip validation for empty rows
        if ($this->shouldSkipRow) {
            return [];
        }

        return [
            'claim_number' => [
                'required',
                function ($attribute, $value, $fail) {
                    // Use the actual validated data instead of currentRow
                    $row = $this->currentRow;

                    $debtorNid = $row['debtor_id'];
                    $amount = (float) $row['claim_amount'];
                    $subClaimNumber = isset($row['sub_claim_number']) && $row['sub_claim_number'] !== ''
                            ? $row['sub_claim_number']
                            : null;
                    // Check for duplicate
                    $isDuplicate = $this->claimService->isDuplicateClaim(
                        $value ?? '',
                        $debtorNid,
                        $amount,
                        $subClaimNumber
                    )->exists();

                    if ($isDuplicate) {
                        $fail('Duplicate claim detected: claim_number=' . $value .
                            ', debtor_nid=' . ($row['debtor_id'] ?? 'N/A') .
                            ', amount=' . ($row['claim_amount'] ?? 'N/A') .
                            ', sub_claim_number=' . ($row['sub_claim_number'] ?? 'N/A'));
                    }
                }
            ],
            'claim_amount' => ['required', 'numeric', 'min:0'],

            'claim_type' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if ($value === null || trim($value) === '') {
                        return; // allow empty value as it will fallback later
                    }

                    if (!ClaimType::tryFrom(strtolower(trim($value)))) {
                        $fail("Invalid '{$attribute}' value '{$value}'.");
                    }
                }
            ],

            'debtor_type' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (DebtorType::tryFrom(strtolower(trim($value ?? ''))) === null) {
                        $validValues = implode(', ', array_column(DebtorType::cases(), 'value'));
                        $fail("Invalid '{$attribute}' value '{$value}'. Must be one of: {$validValues}.");
                    }
                }
            ],

            'debtor_id' => ['required'],
            'sub_claim_number' => ['nullable'],
            'employee_email' => [
                'nullable',
                'email',
                function ($attribute, $value, $fail) {
                    if (is_string($value) && !empty($value)) {
                        $userExists = User::where('email', $value)->exists();
                        if (!$userExists) {
                            $fail("The employee email '{$value}' does not exist in the system");
                        }
                    }
                }
            ],

            'employee_name' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if (!empty($value)) {
                        $userExists = User::whereRaw('LOWER(name) = ?', [strtolower($value)])->exists();
                        if (!$userExists) {
                            $fail("The employee name '{$value}' does not exist in the system");
                        }
                    }
                }
            ],
            'policy_number' => ['required'],
            'mistake_percentage' => ['nullable', 'integer', 'between:1,100'],
            'accident_date' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (is_numeric($value)) {
                        return; // Valid Excel serial date
                    }
                    if (\DateTime::createFromFormat('d/m/Y', $value) === false && strtotime($value) === false) {
                        $fail("The accident date '{$value}' is not a valid date. Expected a date (e.g. 28/02/2026).");
                    }
                },
            ],
            'intimation_date' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if (empty($value)) {
                        return;
                    }

                    if (!is_numeric($value) && \DateTime::createFromFormat('d/m/Y', $value) === false && strtotime($value) === false) {
                        $fail("The '{$attribute}' value '{$value}' is not a valid date. Expected a date (e.g. 28/02/2026).");
                        return;
                    }

                    // Compare against accident_date, if it's present and itself valid
                    // (accident_date's own rule reports invalid-format errors separately).
                    $accidentDateRaw = $this->currentRow['accident_date'] ?? null;

                    if (empty($accidentDateRaw)) {
                        return;
                    }

                    $accidentDateIsValid = is_numeric($accidentDateRaw)
                        || \DateTime::createFromFormat('d/m/Y', $accidentDateRaw) !== false
                        || strtotime($accidentDateRaw) !== false;

                    if (!$accidentDateIsValid) {
                        return;
                    }

                    $intimationDate = is_numeric($value)
                        ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)
                        : (\DateTime::createFromFormat('d/m/Y', $value) ?: new \DateTime($value));

                    $accidentDate = is_numeric($accidentDateRaw)
                        ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($accidentDateRaw)
                        : (\DateTime::createFromFormat('d/m/Y', $accidentDateRaw) ?: new \DateTime($accidentDateRaw));

                    if ($intimationDate->format('Y-m-d') < $accidentDate->format('Y-m-d')) {
                        $fail("The intimation date '{$value}' must be on or after the accident date '{$accidentDateRaw}'.");
                    }
                },
            ],
            'created_at' => ['nullable', $this->optionalImportDateRule()],
            'assigned_at' => ['nullable', $this->optionalImportDateRule()],
            'recovery_reason' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    // Only validate if value is present
                    if (empty($value)) {
                        return;
                    }

                    if ($this->getReturnReasonId($value) === null) {
                        $fail("The '{$attribute}' value '{$value}' is not a valid return reason.");
                    }
                },
            ],
            'debtor_phone_number' => ['nullable', 'phone:SA,INTERNATIONAL'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'claim_number.required' => 'Claim number is required',
            'claim_amount.required' => 'Claim amount is required',
            'debtor_id.required' => 'Debtor ID number is required',
            'debtor_type.required' => 'Debtor type is required',
            'employee_email.email' => 'Invalid email address format',
            'policy_number.required' => 'policy_number is required',
            'mistake_percentage.integer' => 'mistake_percentage must be an integer',
            'mistake_percentage.between' => 'mistake_percentage must be between 1 and 100',
            'accident_date.required' => 'Accident date is required',
            'claim_amount.numeric' => 'Claim amount must be a number',
            'claim_amount.min' => 'Claim amount must be 0 or greater',
            'recovery_reason.required' => 'Recovery reason is required when debtor type is insured',
            'debtor_phone_number.phone' => 'The debtor phone number is invalid',
        ];
    }

    public function model(array $row)
    {
        // Skip empty rows - return null to prevent model creation
        if ($this->shouldSkipRow) {
            return null;
        }

        $currentRowNumber = $this->getRowNumber();
        $party_mobile_number = isset($row['debtor_phone_number']) && $row['debtor_phone_number'] !== '' ? $this->parseMobileNumber($row['debtor_phone_number']) : '';

        $intimationDateRaw = $row['intimation_date'] ?? null;
        $intimation_date = !empty($intimationDateRaw)
            ? (is_numeric($intimationDateRaw)
                ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($intimationDateRaw)->format('Y-m-d')
                : ((\DateTime::createFromFormat('d/m/Y', $intimationDateRaw) ?: new \DateTime($intimationDateRaw))->format('Y-m-d')))
            : null;

        $accidentDateRaw = $row['accident_date'];
        $accident_date = is_numeric($accidentDateRaw)
            ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($accidentDateRaw)
            : (\DateTime::createFromFormat('d/m/Y', $accidentDateRaw) ?: new \DateTime($accidentDateRaw));

        $createdAt = $this->parseOptionalImportDate($row['created_at'] ?? null);
        $assignedAt = $this->parseOptionalImportDate($row['assigned_at'] ?? null);

        $subClaimNumber = $subClaimNumber = (isset($row['sub_claim_number']) && $row['sub_claim_number'] !== '')
            ? $row['sub_claim_number']
            : null;

        $claim = [
            'user_id' => $this->user->id,
            'client_id' => $this->user->client_id,
            'file_id' => $this->claims_file->id,
            'insertion_type' => ClaimInsertionType::EXCEL_FILE,
            'policy_number' => $row['policy_number'],
            'claim_number' => $row['claim_number'],
            'sub_claim_number' => $subClaimNumber,
            'amount' => $row['claim_amount'],
            'type' => (ClaimType::tryFrom(strtolower(trim($row['claim_type'] ?? ''))) ?? ClaimType::INDIVIDUAL)->value,
            'debtor_name' => $row['debtor_name'] ?? '',
            'debtor_type' => $row['debtor_type'],
            'debtor_nid' => $row['debtor_id'],
            'debtor_phone' => $party_mobile_number,
            'plate_number' => $row['plate_number'] ?? '',
            'victim_plate_number' => $row['victim_plate_number'] ?? '',
            'accident_number' => $row['accident_number'],
            'intimation_date' => $intimation_date,
            'accident_date' => $accident_date->format('Y-m-d'),
            'is_draft' => true,
            'car_type' => $row['vehicle_make'] . ' - ' . $row['vehicle_model'],
            'mistake_percentage' => isset($row['mistake_percentage']) && $row['mistake_percentage'] !== ''
                ? (int) $row['mistake_percentage']
                : null,
            'remark' => $row['remarks'],
            'return_reason' => isset($row['recovery_reason'])
                ? $this->getReturnReasonId($row['recovery_reason'])
                : null,
        ];

        if ($createdAt !== null) {
            $claim['created_at'] = $createdAt;
        }




        // Get collector ID by email or name
        $collectorId = $this->getCollectorId(
            $row['employee_email'] ?? null,
            $row['employee_name'] ?? null
        );


        $claim['city_id'] = null;

        // Trim and check if city has actual content
        if (!empty(trim($row['city'] ?? ''))) {
            $cityName = Str::lower(trim($row['city']));

            $city = City::where('name', $cityName)->first();

            if ($city) {
                $claim['city_id'] = $city->id;
            } else {
                try {
                    $city = City::create(['name' => $cityName]);
                    $claim['city_id'] = $city->id;
                } catch (\Exception $e) {
                    Log::warning("Failed to create city '{$cityName}': " . $e->getMessage());
                }
            }
        }


        $claim['claim_number'] = Str::of($claim['claim_number'])->trim();
        $claim['debtor_phone'] = Str::of($claim['debtor_phone'])->trim();
        $claim['debtor_nid'] = Str::of($claim['debtor_nid'])->trim();
        $claim['amount'] = Str::of($claim['amount'])->trim();
        $claim['sub_claim_number'] = $subClaimNumber ? Str::of($subClaimNumber)->trim() : null;

        $newClaim = new Claim($claim);
        $newClaim->save();

        if ($collectorId !== null) {
            self::$claimAssignments[] = [
                'claim_id' => $newClaim->id,
                'collector_id' => $collectorId,
                'supervisor_id' => $this->user->id ?? null,
                'assign_date' => $assignedAt,
            ];
        }

        return $newClaim;
    }

    public function startRow(): int
    {
        return 2;
    }

    public function chunkSize(): int
    {
        return 200;
    }


    // private function calculateLibility(string $deptorType, int $mistake_percentage): int
    // {
    //     return $deptorType == DebtorType::INSURED->value ? $mistake_percentage : 100 - $mistake_percentage;
    // }

    private function getCollectorId(?string $collectorEmail, ?string $collectorName): ?int
    {

        if (!empty($collectorEmail)) {
            $user = $this->collectors->filter(function ($item) use ($collectorEmail) {
                return strtolower($item['email']) == strtolower($collectorEmail);
            })->first();

            if ($user) {
                return $user->id;
            }
        }


        if (!empty($collectorName)) {
            $user = $this->collectors->filter(function ($item) use ($collectorName) {
                return strtolower($item['name']) == strtolower($collectorName);
            })->first();

            if ($user) {
                return $user->id;
            }
        }

        return null;
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
                if (
                    mb_strpos($normalizedDb, $normalizedInput) !== false ||
                    mb_strpos($normalizedInput, $normalizedDb) !== false
                ) {
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

    private function optionalImportDateRule(): \Closure
    {
        return function ($attribute, $value, $fail) {
            try {
                $this->parseOptionalImportDate($value);
            } catch (\InvalidArgumentException) {
                $fail("The '{$attribute}' value '{$value}' is not a valid date. Expected mm/dd/yyyy.");
            }
        };
    }

    protected function parseOptionalImportDate(mixed $value): ?string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        if (is_numeric($value)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        $value = trim((string) $value);

        foreach (['m/d/Y', 'Y-m-d'] as $format) {
            $date = \DateTime::createFromFormat('!'.$format, $value);
            $errors = \DateTime::getLastErrors();

            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        throw new \InvalidArgumentException("Invalid import date: '{$value}'.");
    }

    /**
     * Determine if a row is completely empty.
     *
     * A row is considered empty if ALL cells are null, empty string, or whitespace-only.
     * This checks every field in the row, not just required fields.
     *
     * @param array $row The row data from Excel
     * @return bool True if all cells are empty, false if any cell has data
     */
    private function isEmptyRow(array $row): bool
    {
        try {
            foreach ($row as $value) {
                if ($value !== null && trim((string) $value) !== '') {
                    return false;
                }
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function runPostImportAssignments(): void
    {
        $assigningService = new AssigningService;
        $usersToBeNotified = [];

        foreach (self::$claimAssignments as $assignment) {
            try {
                $assigningService->create([
                    'claims_id' => [$assignment['claim_id']],
                    'to' => [$assignment['collector_id']],
                    'supervisor_id' => $assignment['supervisor_id'],
                    'assign_date' => $assignment['assign_date'] ?? null,
                ]);
                $usersToBeNotified[] = $assignment['collector_id'];
            } catch (\Throwable $e) {
                \Log::error('Failed to assign claim during import', [
                    'claim_id' => $assignment['claim_id'],
                    'collector_id' => $assignment['collector_id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $usersToBeNotified = collect($usersToBeNotified)->unique()->all();

        foreach ($usersToBeNotified as $userToBeNotified) {
            try {
                NotificationService::create([
                    'title_ar' => __('notification.many_claims_have_been_assigned', [], 'ar'),
                    'title_en' => __('notification.many_claims_have_been_assigned', [], 'en'),
                    'description_ar' => __('notification.many_claims_have_been_assigned', [], 'ar'),
                    'description_en' => __('notification.many_claims_have_been_assigned', [], 'en'),
                    'regarding' => NotificationType::CLAIM_ASSIGNED->value,
                    'user_id' => $userToBeNotified,
                    'to' => json_encode([$userToBeNotified]),
                    'links' => json_encode([
                        [
                            'label_ar' => __('notification.go_to_assigned_claims', [], 'ar'),
                            'label_en' => __('notification.go_to_assigned_claims', [], 'en'),
                            'url' => 'claims/',
                        ]
                    ]),
                ]);
            } catch (\Throwable $e) {
                \Log::error('Failed to send assignment notification during import', [
                    'user_id' => $userToBeNotified,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

}
