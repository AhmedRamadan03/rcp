<?php

namespace App\Services\ClaimImport;

use App\Enums\ClaimInsertionType;
use App\Enums\DebtorType;
use App\Enums\FileSource;
use App\Exports\FailedBacklogClaimExport;
use App\Jobs\ProcessClaimImportJob;
use App\Models\City;
use App\Models\Claim;
use App\Models\UploadedClaimsFile;
use App\Models\User;
use App\Services\ClaimService;
use App\Services\FilesUploader;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ClaimImportWithBacklogService
{
    protected ClaimActionRouter $router;
    protected ClaimService $claimService;
    protected array $failedClaims = [];
    protected array $successClaims = [];
    protected $cities;
    protected $collectors;

    public function __construct()
    {
        $this->router = new ClaimActionRouter();
        $this->claimService = new ClaimService();
        $this->cities = City::select('id', 'name')->get();
        $this->collectors = User::select('id', 'name')->get();
    }

    /**
     * Import claims from Excel file with status-based action routing.
     *
     * @param User $user The user performing the import
     * @param UploadedFile $file The Excel file to import
     * @return array Contains 'success', 'failed', 'uploaded_file', 'failed_file_url'
     */
    public function import(User $user, UploadedFile $file): array
    {
        // Validate row count (max 500 rows excluding header)
        $rowCount = Excel::toArray([], $file)[0];
        $dataRowCount = count($rowCount) - 1; // Exclude header row

        if ($dataRowCount > 2000) {
            throw new Exception("File contains {$dataRowCount} rows. Maximum allowed is 500 rows.");
        }

        // Upload file record
        $directory    = 'uploaded-claims-file/' . Carbon::today()->year;
        $originalFile = (new FilesUploader)->upload($file, [
            'source'      => FileSource::UPLOADED_CLAIMS_FILES,
            'directory'   => $directory,
            'uploaded_by' => $user->id,
        ]);

        $uploadedClaimsFile = UploadedClaimsFile::create([
            'user_id'                => $user->id,
            'client_id'              => $user->client_id,
            'uploaded_claims_status' => 'scheduled',
            'file_url'               => $originalFile->local_path ?? $originalFile->cloud_path,
            'original_file_id'       => $originalFile->id,
            'inserted_date'          => Carbon::now(),
        ]);

        // Parse Excel file
        $rows = $this->parseExcel($file);

        // Process each row in its own transaction
        foreach ($rows as $index => $row) {
            try {
                DB::beginTransaction();

                // Check for duplicates
                $isDuplicate = $this->checkDuplicate($row);
                if ($isDuplicate) {
                    throw new Exception('Duplicate claim detected');
                }

                // Create claim
                $claim = $this->createClaim($row, $user, $uploadedClaimsFile);

                // Route to action based on claim_status
                $status = $row['claim_status'] ?? 'registered';

                $this->router->actionRoute($claim, $status);

                DB::commit();
                $this->successClaims[] = $claim;

            } catch (Exception $e) {
                DB::rollBack();
                $this->failedClaims[] = array_merge($row, [
                    'error' => $e->getMessage(),
                    'row_number' => $index + 2, // +2 for header row and 0-index
                ]);
            }
        }

        // Update uploaded file status
        $uploadedClaimsFile->uploaded_claims_status = 'inserted';
        $uploadedClaimsFile->save();

        // Dispatch job to process assignments and notifications in background
        //ProcessClaimImportJob::dispatch($uploadedClaimsFile->id, $user->id);

        // Export failed claims if any
        $failedFileUrl = null;
        if (count($this->failedClaims) > 0) {
            $failedRelative = 'failedClaims/' . $uploadedClaimsFile->id . '/failed.xlsx';
            Excel::store(
                new FailedBacklogClaimExport($this->failedClaims),
                'public/' . $failedRelative,
                'local'
            );

            $failedFile = (new FilesUploader)->registerAndLinkFailedFile(
                $uploadedClaimsFile,
                $failedRelative,
                $user->id
            );
            $uploadedClaimsFile->save();

            $failedFileUrl = route('download', ['key' => $failedFile->uuid]);
        }

        return [
            'success_count' => count($this->successClaims),
            'failed_count' => count($this->failedClaims),
            'claims' => Claim::where('file_id', $uploadedClaimsFile->id)->get(),
            'failed_claims' => $this->failedClaims,
            'failed_file_url' => $failedFileUrl,
            'original_file_url' => route('download', ['key' => $originalFile->uuid]),
        ];
    }

    /**
     * Parse Excel file into array of rows.
     */
    protected function parseExcel(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // First row is headers
        $headers = array_map(function ($header) {
            return Str::snake(strtolower(trim($header ?? '')));
        }, array_shift($rows));

        // Map rows to associative arrays
        $result = [];
        foreach ($rows as $row) {
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }
            $result[] = array_combine($headers, $row);
        }

        return $result;
    }

    /**
     * Check if claim is a duplicate.
     */
    protected function checkDuplicate(array $row): bool
    {
        $claimNumber = $row['claim_number'] ?? '';
        $debtorNid = $row['id_number'] ?? '';
        $amount = $row['outstanding_recovery'] ?? 0;
        $subClaimNumber = $row['sub_claim_number'] ?? null;

        return $this->claimService->isDuplicateClaim(
            $claimNumber,
            $debtorNid,
            $amount,
            $subClaimNumber
        )->exists();
    }

    /**
     * Create a claim from row data.
     */
    protected function createClaim(array $row, User $user, UploadedClaimsFile $claimsFile): Claim
    {
        $partyMobileNumber = '';
        if (!empty($row['od_mobile_no'])) {
            $rawPhone = trim((string) $row['od_mobile_no']);
            $isValidPhone = false;
            $isSa = false;
            try {
                $phone = new \Propaganistas\LaravelPhone\PhoneNumber($rawPhone, 'SA');
                $isValidPhone = $phone->isValid();
                $isSa = $isValidPhone && $phone->getCountry() === 'SA';
            } catch (\Throwable $e) {
                throw new Exception("The 'od_mobile_no' value '{$row['od_mobile_no']}' is not a valid phone number.");
            }
            if (!$isValidPhone) {
                throw new Exception("The 'od_mobile_no' value '{$row['od_mobile_no']}' is not a valid phone number.");
            }

            $partyMobileNumber = $isSa ? $phone->formatE164() : $rawPhone;
        }

        $registrationDate = $this->parseExcelDate($row['claim_registration_date'] ?? null);
        $dateOfLoss = $this->parseExcelDate($row['date_of_loss'] ?? null);

        $subClaimNumber = $row['sub_claim_number'] ?? null;

        $claimData = [
            'client_id' => $user->client_id,
            'file_id' => $claimsFile->id,
            'insertion_type' => ClaimInsertionType::EXCEL_FILE->value,
            'is_backlog' => true,
            'policy_number' => $row['policy_number'] ?? '',
            'claim_number' => Str::of($row['claim_number'] ?? '')->trim(),
            'sub_claim_number' => $subClaimNumber ? Str::of($subClaimNumber)->trim() : null,
            'amount' => Str::of($row['outstanding_recovery'] ?? 0)->trim(),
            'type' => $row['claim_type'] ?? '',
            'debtor_name' => $row['driver_name'] ?? '',
            'debtor_type' => $row['debtor_type'] ?? '',
            'plate_number' => $row['od_plate_number'] ?? '',
            'policy_holder' => $row['policy_holder'] ?? '',
            'date_of_loss' => $dateOfLoss,
            'accident_number' => $row['accident_number'] ?? '',
            'intimation_date' => $registrationDate?->format('Y-m-d'),
            'accident_date' => $registrationDate?->format('Y-m-d'),
            'recovered_amount' => $row['recovered_amount'] ?? 0,
            'is_draft' => 1,
            'car_type' => ($row['od_vehicle_vehicle_make'] ?? '') . ' - ' . ($row['od_vehicle_vehicle_model'] ?? ''),
            'mistake_percentage' => $this->calculateLiability($row['debtor_type'] ?? '', $row['liability'] ?? 0),
            'remark' => $row['remarks'] ?? '',
        ];

        // Set debtor info based on type
        if (($row['debtor_type'] ?? '') === DebtorType::INSURED->value) {
            $claimData['debtor_nid'] = Str::of($row['id_number'] ?? '')->trim();
            $claimData['debtor_phone'] = Str::of($partyMobileNumber)->trim();
        } else {
            $claimData['debtor_nid'] = '';
            $claimData['debtor_phone'] = '';
        }

        // Set user_id
        if (is_string($row['employee_name'] ?? null)) {
            $claimData['user_id'] = $this->getCollectorId($row['employee_name']);
        } else {
            $claimData['user_id'] = auth()->user()->id;
        }

        // Set city
        $claimData['city_id'] = $this->getCityId($row['area'] ?? '');

        $claim = Claim::create($claimData);

        return $claim;
    }

    /**
     * Parse Excel date value.
     */
    protected function parseExcelDate($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::instance(
                \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)
            );
        }

        return Carbon::parse($value);
    }

    /**
     * Calculate liability percentage.
     */
    protected function calculateLiability(string $debtorType, int $liability): int
    {
        return $debtorType === DebtorType::INSURED->value ? $liability : 100 - $liability;
    }

    /**
     * Get collector ID by name.
     */
    protected function getCollectorId(string $collectorName): int
    {
        $user = $this->collectors->filter(function ($item) use ($collectorName) {
            return strtolower($item['name']) === strtolower($collectorName);
        })->first();

        return $user?->id ?? auth()->user()->id;
    }

    /**
     * Get or create city by name.
     */
    protected function getCityId(string $area): int
    {
        $city = $this->cities->filter(function (City $city) use ($area) {
            return Str::contains(Str::lower($area), $city->name);
        })->first();

        if ($city) {
            return $city->id;
        }

        // Create new city
        $newCity = City::create(['name' => Str::lower($area)]);
        $this->cities = City::select('id', 'name')->get();
        return $newCity->id;
    }
}
