<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

class FailedBulkAssignExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
{
    protected $failedRows;
    protected $excelFile;

    /**
     * @param array $failedRows Array of failed rows with their reasons
     */
    public function __construct(array $failedRows)
    {
        $this->failedRows = collect($failedRows);

    }

    /**
     * Return the collection of failed rows
     *
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->failedRows->map(function ($failedRow) {
            return [
                'claim_number' => $failedRow['claim_number'] ?? '',
                'sub_claim_number' => $failedRow['sub_claim_number'] ?? '',
                'to_email' => $failedRow['to_email'] ?? '',
                'to_name' => $failedRow['to_name'] ?? '',
                'failure_reasons' => is_array($failedRow['reasons'])
                    ? implode(', ', $failedRow['reasons'])
                    : $failedRow['reasons'],
            ];
        });
    }

    /**
     * Define the header row
     *
     * @return array
     */
    public function headings(): array
    {
        return [
            'Claim Number',
            'Sub Claim Number',
            'To Email',
            'To Name',
            'Failure Reasons',
        ];
    }

    /**
     * Apply styles to the worksheet
     * - Header row: Blue background with white text
     * - Failed rows: Red background with white text
     *
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        $styles = [];

        // Style for header row
        $styles[1] = [
            'font' => [
                'bold' => true,
                'color' => ['argb' => Color::COLOR_WHITE],
            ],
            'alignment' => [
                'horizontal' => 'center',
                'vertical' => 'center',
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['argb' => '5A56E3'], // Blue background
            ],
        ];

        // Style for all data rows (failed rows in red)
        $rowCount = $this->failedRows->count() + 1; // +1 for header
        for ($row = 2; $row <= $rowCount; $row++) {
            $styles[$row] = [
                'font' => [
                    'color' => ['argb' => Color::COLOR_WHITE],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['argb' => 'FFE74C3C'], // Red background
                ],
                'alignment' => [
                    'vertical' => 'center',
                ],
            ];
        }

        return $styles;
    }

    /**
     * Define column widths
     *
     * @return array
     */
    public function columnWidths(): array
    {
        return [
            'A' => 20, // Claim Number
            'B' => 20, // Sub Claim Number
            'C' => 30,  // To Email
            'D' => 30,  // To Name
            'E' => 50, // Failure Reasons
        ];
    }
}
