<?php

namespace App\Imports;

use App\Enums\ClaimInsertionType;
use App\Enums\ClaimStatus;
use App\Enums\ClaimType;
use App\Models\Claim;
use App\Models\UploadedClaimsFile;
use App\Models\User;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class UploadClaimsImport implements SkipsOnFailure, ToModel, WithStartRow, WithValidation
{
    use Importable, SkipsFailures;

    public function __construct(public User $user, public UploadedClaimsFile $claims_file) {}

    public function startRow(): int
    {
        return 2;
    }

    public function model(array $row)
    {

        return new Claim([
            'client_id' => $this->user->client_id,
            'user_id' => $this->user->id,
            'file_id' => $this->claims_file->id,
            'insertion_type' => ClaimInsertionType::EXCEL_FILE,
            'amount' => $row[22],
            'type' => ClaimType::INDIVIDUAL,
            'policy_number' => $row[4],
            'claim_number' => $row[3],
            'debtor_name' => $row[19],
            'debtor_nid' => $row[18],
            'debtor_phone' => $this->parseMobileNumber($row[32] ?? null),
            'accident_date' => Carbon::parse($row[7])->format('Y-m-d'),
            'status' => $this->getStatus($row[23]),
        ]);

    }

    public function getStatus($status)
    {
        if ($status == 'recovered') {
            return ClaimStatus::DONE;
        } elseif ($status == 'closed') {
            return ClaimStatus::REJECTED;
        } else {
            return ClaimStatus::REGISTERED;
        }
    }

    public function rules(): array
    {
        return [
            '3' => 'unique:claims,claim_number',
            '32' => ['nullable', 'phone:SA,INTERNATIONAL'],
        ];
    }

    protected function parseMobileNumber($mobileNumber): ?string
    {
        if (empty($mobileNumber)) {
            return null;
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
}
