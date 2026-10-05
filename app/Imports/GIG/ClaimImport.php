<?php

namespace App\Imports\GIG;

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


class ClaimImport implements SkipsOnFailure, ToModel, WithHeadingRow, WithStartRow, WithValidation, WithEvents
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

    public function __construct(public User $currentUser, public UploadedClaimsFile $claims_file)
    {
        //$this->cities = City::select('id', 'name')->get();
        $this->collectors = User::select('id', 'name','supervisor_id')->get();
        $this->claimService = new ClaimService;
    }

    public function prepareForValidation($data, $index)
        {
            // Reset flag for each row
            $this->shouldSkipRow = false;

            // Preserve current row for duplicate checking
            $this->currentRow = $data;

            // Check if this is an empty row
            $this->shouldSkipRow = $this->isEmptyRow($data);

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

                        $debtorNid = $row['id_number'];
                        $amount = (float) $row['outstanding_recovery'] ;
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
                                  ', debtor_nid=' . ($row['id_number'] ?? 'N/A') .
                                  ', amount=' . ($row['outstanding_recovery'] ?? 'N/A') .
                                  ', sub_claim_number=' . ($row['sub_claim_number'] ?? 'N/A'));
                        }
                    }
                ],
                'outstanding_recovery' => ['required'],
                'id_number' => ['required'],
                'driver_name' => ['required'],
                'sub_claim_number' => ['nullable'],
                'employee_email' => [
                    'nullable',
                    'email',
                    function ($attribute, $value, $fail) {
                        // Only validate if email is provided
                        if (is_string($value) && !empty($value)) {
                            $userExists = User::where('email', $value)->exists();
                            if (!$userExists) {
                                $fail("The employee email '{$value}' does not exist in the system");
                            }
                        }
                    }
                ],

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
                'policy_number' => ['required'],
                'liability' => ['nullable', 'integer' , 'between:1,100'],
                'accident_date' => ['required'],
                'intimation_date' => [
                    'nullable',
                    'date',
                    'after_or_equal:accident_date',
                ],
                'recovery_reason' => [
                    Rule::requiredIf(fn() => ($this->currentRow['debtor_type'] ?? null) === DebtorType::INSURED->value),
                    'nullable',
                    function ($attribute, $value, $fail) {
                        // Only validate if value is present
                        if (empty($value)) {
                            return;
                        }

                        // Build a list of all valid ReturnReason strings (both languages or just one)
                        $validReasons = collect(ReturnReason::cases())
                            ->map(fn($case) => ReturnReason::getString($case->value))
                            ->filter()
                            ->values()
                            ->toArray();

                        if (!in_array($value, $validReasons)) {
                            $fail("The '{$attribute}' value '{$value}' is not a valid return reason.");
                        }
                    },
                ],
                'od_mobile_no' => ['nullable', 'phone:SA,INTERNATIONAL'],
            ];
        }

    public function customValidationMessages(): array
    {
        return [
            'claim_number.required' => 'Claim number is required',
            'id_number.required' => 'Debtor ID number is required',
            'driver_name.required' => 'Driver name is required',
            'outstanding_recovery.required' => 'Outstanding recovery amount is required',
            'employee_email.email' => 'Invalid email address format',
            'policy_number.required' => 'policy_number is required',
            'liability.integer' => 'Liability must be an integer',
            'liability.between' => 'Liability must be between 1 and 100',
            'accident_date.required' => 'Accident date is required',
            'recovery_reason.required' => 'Recovery reason is required when debtor type is insured',
            'od_mobile_no.phone' => 'The mobile number is invalid',
        ];
    }

    public function model(array $row)
        {
            //dd($row);
            // Skip empty rows - return null to prevent model creation
            if ($this->shouldSkipRow) {
                return null;
            }

            $currentRowNumber = $this->getRowNumber();
            $party_mobile_number = $this->parseMobileNumber($row['od_mobile_no'] ?? null);

            $intimation_date = !empty($row['intimation_date'])
            ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row['intimation_date'])->format('Y-m-d')
            : null;

            $accident_date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row['accident_date']);

            $date_of_loss = !empty($row['date_of_loss'])
            ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row['date_of_loss'])->format('Y-m-d')
            : null;

            $subClaimNumber = $subClaimNumber = (isset($row['sub_claim_number']) && $row['sub_claim_number'] !== '')
            ? $row['sub_claim_number']
            : null;

            $claim = [
                'client_id' => $this->currentUser->client_id,
                //            'user_id' => $this->user->id,
                'file_id' => $this->claims_file->id,
                'insertion_type' => ClaimInsertionType::EXCEL_FILE,
                'policy_number' => $row['policy_number'],
                'claim_number' => $row['claim_number'],
                'sub_claim_number' => $subClaimNumber,
                'amount' => $row['outstanding_recovery'],
                'type' => (ClaimType::tryFrom(strtolower(trim($row['claim_type'] ?? ''))) ?? ClaimType::INDIVIDUAL)->value,
                'debtor_name' => $row['driver_name'],
                'debtor_type' => (DebtorType::tryFrom(strtolower(trim($row['debtor_type'] ?? ''))) ?? DebtorType::THIRD_PARTY)->value,
                'debtor_nid' => $row['id_number'],
                'debtor_phone' => $party_mobile_number,
                'plate_number' => $row['od_plate_number'] ?? '',
                'victim_plate_number' => $row['victim_plate_number'] ?? '',
                'policy_holder' => $row['policy_holder'] ?? '', //
                'date_of_loss' => $date_of_loss, //
                'accident_number' => $row['accident_number'] ?? '', //

                'intimation_date' => $intimation_date,
                'accident_date' => $accident_date,
                'recovered_amount' => $row['recovered_amount'] ?? '', //
                'is_draft' => true,
                'car_type' => isset($row['vehicle_make'], $row['vehicle_model'])
                    ? $row['vehicle_make'] . ' - ' . $row['vehicle_model']
                    : ($row['vehicle_make'] ?? $row['vehicle_model'] ?? null),
                //'mistake_percentage' => $this->calculateLibility($this->deptorType($row['debtor_type']), $row['liability']),
                'mistake_percentage' => isset($row['liability']) && $row['liability'] !== ''
                    ? (int) $row['liability']
                    : null,
                //            'user_id' => $this->getCollectorId($row["employee_name"]),
                'remark' => $row['remarks'] ?? '',
                'return_reason' => isset($row['recovery_reason'])
                    ? $this->getReturnReasonId($row['recovery_reason'])
                    : null,
            ];


            if (is_string($row['employee_email'])) {
                $claim['user_id'] = $this->getCollectorId($row['employee_email'] );
            } else {

                $claim['user_id'] = auth()->user()->id;
            }

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
                    \Log::warning("Failed to create city '{$cityName}': " . $e->getMessage());
                }
            }
        }


            $claim['claim_number'] = Str::of($claim['claim_number'])->trim();
            $claim['debtor_phone'] = Str::of($claim['debtor_phone'])->trim();
            $claim['debtor_nid'] = Str::of($claim['debtor_nid'])->trim();
            $claim['amount'] = Str::of($claim['amount'])->trim();
            $claim['sub_claim_number'] = $subClaimNumber ? Str::of($subClaimNumber)->trim() : null;

            return new Claim($claim);
        }

    public function startRow(): int
    {
        return 2;
    }

    //    public function rules(): array
    //    {
    //        return [
    //            'claim_number' => 'unique:claims,claim_number',
    //        ];
    //    }

    private function deptorType(string $deptorType): string
    {
        return $deptorType == 'Right of recourse' ? DebtorType::INSURED->value : DebtorType::THIRD_PARTY->value;
    }

    // private function calculateLibility(string $deptorType, int $libility): int
    // {
    //     return $deptorType == DebtorType::INSURED->value ? $libility : 100 - $libility;
    // }

    private function getCollectorId(string $colloctorEmail ): int
    {
        $user = User::where('email', $colloctorEmail)->first();
        return $user ? $user->id : $this->currentUser->id ;

    }

    private function getReturnReasonId(string $value): ?int
    {
        foreach (ReturnReason::cases() as $case) {

            if (ReturnReason::getString($case->value) === $value) {
                return $case->value;
            }
        }

        return null;
    }

    protected function parseMobileNumber($mobileNumber): string
    {
        if (empty($mobileNumber)) {
            return '';
        }
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

    public static function afterImport(AfterImport $event)
    {
        // Get the reader to access the import instance
        $import = $event->getConcernable();

        if (!$import instanceof self) {
            return;
        }

        $file_id = $import->claims_file->id;
        $claims = Claim::where('file_id', $file_id)->select(['id', 'user_id'])->get();

        $usersToBeNotified = [];
        $assigningService = new AssigningService;
        $collectors = $import->collectors;

        foreach ($claims as $claim) {
            $assignedCollector = $collectors->firstWhere('id', $claim->user_id);

            if ($assignedCollector) {
                $assigningService->create([
                    'claims_id' => [$claim->id],
                    'to' => [$claim->user_id],
                    'supervisor_id' => $assignedCollector->supervisor_id ?? null,
                ]);
                $usersToBeNotified[] = $claim->user_id;
            }

            $claim->user_id = $import->currentUser->id;
            $claim->save();

        }
        $usersToBeNotified = collect($usersToBeNotified)->unique()->all();

        $uploadedClaimFile = $import->claims_file;
        foreach ($usersToBeNotified as $userToBeNotified) {
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
                    ],
                ]),
            ]);
        }

    }

    }
