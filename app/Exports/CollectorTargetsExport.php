<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CollectorTargetsExport implements FromCollection, WithColumnWidths, WithHeadings, WithStyles
{
    protected $targetsHistory;

    public function __construct($targetsHistory)
    {
        $this->targetsHistory = $targetsHistory;
    }

    public function collection()
    {
        return collect($this->targetsHistory)->map(function ($targetsHistory) {
            return [
                'target' => $targetsHistory['target'] ?? null,
                'achieved' => $targetsHistory['achieved'] ?? null,
                'remaining' => $targetsHistory['remaining'] ?? null,
                'start_date' => $targetsHistory['start_date'] ?? null,
                'end_date' => $targetsHistory['end_date'] ?? null,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Target',
            'Achieved',
            'Remaining',
            'Start Date',
            'End Date',
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
            'E' => 30,
        ];
    }
}
