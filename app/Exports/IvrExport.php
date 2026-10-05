<?php

namespace App\Exports;

use App\Models\Ivr;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class IvrExport implements 
    FromQuery,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithColumnWidths, 
     WithChunkReading,
    ShouldQueue
{
    public function query()
    {
        return Ivr::with([
            'paymentLink.payment.claim'
        ])->filter(); // لو عندك scope filter
    }

    public function map($item): array
    {
        return [
            $item->paymentLink?->payment?->claim?->claim_number,
            $item->paymentLink?->payment?->claim?->sub_claim_number,
            $item->paymentLink?->payment?->claim?->debtor_phone,
            $item->retry_number,
            $item->status,
            $item->created_at,
        ];
    }

    public function chunkSize(): int
    {
        return 1000; // يقرأ كل مرة 1000 صف بس
    }

    public function headings(): array
    {
        return [
            "Claim Number",
            "Sub Claim Number",
            "Phone Number",
            "Retry Number",
            "Status",
            "Send Date",
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['argb' => '5A56E3']
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 50,
            'B' => 30,
            'C' => 30,
            'D' => 30,
            'E' => 30,
            'F' => 30,
        ];
    }
}