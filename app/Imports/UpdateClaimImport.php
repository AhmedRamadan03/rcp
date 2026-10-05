<?php

namespace App\Imports;

use App\Enums\ClaimInsertionType;
use App\Enums\ClaimStatus;
use App\Enums\ClaimType;
use App\Enums\InvoiceStatus;
use App\Enums\UserType;
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

class UpdateClaimImport implements SkipsOnFailure, WithStartRow
{
    use Importable, SkipsFailures;

    public function startRow(): int
    {
        return 2;
    }

    public function updateClaim(array $rowData, string $invoiceStatus)
    {
     

        $claimNumber = (string) trim($rowData['claim_number']); 
        $claimId = !empty($rowData['claim_id']) ? (int) $rowData['claim_id'] : null;

        if ($claimId) {
            // لو الـ id مبعوت، دور بيه   
            $claim = Claim::where('id', $claimId)->first();

            if (!$claim) {
                throw new \Exception('Claim not found');
            }
        } else {
            // لو مفيش id، دور بس برقم الكلايم
            $claims = Claim::where('claim_number', $claimNumber)->get();

            if ($claims->isEmpty()) {
                throw new \Exception('Claim not found');
            }

            if ($claims->count() > 1) {
                throw new \Exception('More than one claim found with this claim number');
            }

            $claim = $claims->first();
        }

        $assignedUser = $claim->assignees->where('is_active', 1)->first();

        if ($claim->status == ClaimStatus::REJECTED->value) {
            throw new \Exception('Claim is Canceled');
        }

        // Claim متسجل، لسه ما مرش عليه 30 يوم، وإما مفيش حد متعين عليه
        // أو المتعين عليه مستخدم داخلي (INTERNAL_USER)
        if (
            $claim->status == ClaimStatus::REGISTERED->value
            && $claim->created_at->diffInDays(Carbon::now()) < 30
            && (!$assignedUser || $assignedUser->toUser->user_type == UserType::INTERNAL_USER->value)
        ) {
            throw new \Exception('Claim is outstanding and not passed 30 days');
        }

        $claim->update([
            'invoice_status' => $invoiceStatus,
            'invoice_date' => $invoiceStatus != InvoiceStatus::PENDING->value ? Carbon::now() : null,
        ]);

        return $claim;
    }
}