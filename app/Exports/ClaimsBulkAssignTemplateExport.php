<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class ClaimsBulkAssignTemplateExport implements
    FromArray,
    WithHeadings,
    WithTitle,
    ShouldAutoSize,
    WithColumnWidths
{
    /**
     * @return array
     */
    public function array(): array
    {
        return [];
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Claim Number',
            'Sub Claim Number',
            'To Email',
            'To Name',
        ];
    }

    /**
     * @return array
     */
    public function columnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 30,
            'C' => 50,
            'D' => 50,
        ];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Claims Bulk Assign Template';
    }
}
