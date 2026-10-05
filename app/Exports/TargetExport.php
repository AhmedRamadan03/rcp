<?php

namespace App\Exports;

use App\Models\User;
use App\Http\Resources\TargetResource;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles; 
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class TargetExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
{
    protected $collectors;

    public function __construct($collectors)
    {
        $this->collectors = $collectors;
    }

    public function collection()
    {
        return $this->collectors->map(function ($collector) {
            return [
                'name'           => $collector->name,
                'current_target' => $collector->currentTarget->target ?? null,
                'current_achieved' => $collector->currentTarget->achieved ?? null,
                'current_remaining' => $collector->currentTarget->remaining ?? null,
                'previous_target' => $collector->previousTarget->target ?? null,
                'previous_achieved' => $collector->previousTarget->achieved ?? null,
                'previous_remaining' => $collector->previousTarget->remaining ?? null,
                'previous_status' => $collector->previousTarget->status ?? null,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Name',
            'Current Target',
            'Current Achieved',
            'Current Remaining',
            'Previous Target',
            'Previous Achieved',
            'Previous Remaining',
            'Previous Status',
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
                'font' => ['bold' => true,'color' => ['argb' => 'FFFFFFFF']],
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
            
        ];
    }
}
