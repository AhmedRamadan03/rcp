<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TargetDataExport implements FromCollection, WithColumnWidths, WithHeadings, WithStyles
{
    protected $targetData;

    public function __construct($targetData)
    {
        $this->targetData = $targetData;
    }

    public function collection()
    {
        return collect($this->targetData)->map(function ($targetData) {
            return [
                'collected' => $targetData['collected'] ?? null,
                'date' => $targetData['date'] ?? null,
                'claim_number' => $targetData['claim_number'] ?? null,
                'installment_number' => $targetData['installment_number'] ?? null,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Collected',
            'Date',
            'Claim Number',
            'Installment Number',
        ];
    }

    /**
     * styles of table
     *
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
        ];
    }
}
