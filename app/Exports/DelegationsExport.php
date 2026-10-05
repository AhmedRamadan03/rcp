<?php

namespace App\Exports;

use App\Models\Delegation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DelegationsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    /**
     * @param  Collection<Delegation>  $delegations
     */
    public function __construct(public Collection $delegations) {}

    public function collection()
    {
        return $this->delegations;
    }

    public function map($delegation): array
    {
        $startDate = $delegation->start_date ? Carbon::parse($delegation->start_date) : null;
        $endDate = $delegation->end_date ? Carbon::parse($delegation->end_date) : null;

        $duration = 0;
        if ($startDate) {
            $end = $endDate ?? Carbon::now();
            $duration = $startDate->diffInDays($end);
        }

        return [
            $delegation->fromUser?->name ?? '-',
            $delegation->toUser?->name ?? '-',
            $startDate?->format('Y-m-d') ?? '-',
            $endDate?->format('Y-m-d') ?? '-',
            (string) $duration,
            $delegation->is_active ? 'Active' : 'Inactive',
            $delegation->created_at?->format('Y-m-d H:i') ?? '-',
        ];
    }

    public function headings(): array
    {
        return [
            'Delegated From',
            'Delegated To',
            'Start Date',
            'End Date',
            'Duration (Days)',
            'Status',
            'Created At',
        ];
    }

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
            'A' => 25,
            'B' => 25,
            'C' => 15,
            'D' => 15,
            'E' => 15,
            'F' => 15,
            'G' => 20,
        ];
    }
}