<?php

namespace App\Exports;

use App\Enums\ClaimStatus;
use App\Services\ClaimService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;


class ClaimExports implements FromQuery, WithHeadings, WithStyles, WithColumnWidths, ShouldQueue,WithMapping, WithChunkReading
{
    protected $claims;

    protected $filters;
    protected $user;

    public function __construct($filters, $user)
    {
        $this->filters = $filters;
        $this->user = $user;
    }

     public function query()
    {
        $claimService = new ClaimService;
        $claims = $claimService->list($this->filters, false, 10, true, $this->user);

        return $claims->with(['payments', 'assignees.toUser', 'user']);
    }

    
     public function map($claim): array
    {
        $paidAmount = $claim->payments?->first()?->paid_amount ?? 0;

        return [
            $claim->claim_number,
            $claim->sub_claim_number,
            $claim->accident_number,
            $claim->policy_number,
            $claim->debtor_name,
            $claim->debtor_phone,
            $claim->debtor_nid,
            optional($claim->accident_date)->format('d-m-Y'),
            $claim->amount,
            $paidAmount,
            $claim->amount - $paidAmount,
            $claim->intimation_date,
            optional($claim->created_at)->format('d-m-Y'),
            $claim->assignees?->where("is_active", 1)?->first()?->toUser?->name,
            $claim->user?->name,
            ClaimStatus::getString($claim->status),
        ];
    }

    public function chunkSize(): int
    {
        return 1000; // مهم جدًا
    }

    public function headings(): array
    {
        return [
            __("claim.claim_number"),
            __("claim.sub_claim_number"),
            __("claim.accident_number"),
            __("claim.policy_number"),
            __("claim.debtor_name"),
            __("claim.debtor_phone_number"),
            __("claim.debtor_id"),
            __("claim.accident_date"),
            __("claim.claim_amount"),
            __("claim.paid"),
            __("claim.remaining_amount"),
            __("claim.intimation_date"),
            __("claim.submission_date"),
            __("claim.assigned_to"),
            __("claim.created_by"),
            __("claim.status.status"),

        ];
    }


    /**
     * styles of table
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => '5A56E3']],

            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 30,
            'C' => 30,
            'D' => 30,
            'E' => 30,
            'F' => 30,
            'G' => 30,
            'H' => 30,
            'I' => 30,
            'J' => 30,
            'K' => 30,
            'L' => 30,
            'M' => 30,
            'N' => 30,
            'O' => 30,
            'P' => 30,

        ];
    }
}
