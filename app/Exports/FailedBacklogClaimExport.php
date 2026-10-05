<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

class FailedBacklogClaimExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
{
    protected $failedRows;

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
                'policy_number' => $failedRow['policy_number'] ?? '',
                'claim_number' => $failedRow['claim_number'] ?? '',
                'sub_claim_number' => $failedRow['sub_claim_number'] ?? '',
                'id_number' => $failedRow['id_number'] ?? '',
                'od_mobile_no' => $failedRow['od_mobile_no'] ?? '',
                'outstanding_recovery' => $failedRow['outstanding_recovery'] ?? '',
                'driver_name' => $failedRow['driver_name'] ?? '',
                'claim_type' => $failedRow['claim_type'] ?? '',
                'claim_status' => $failedRow['claim_status'] ?? '',
                'od_plate_number' => $failedRow['od_plate_number'] ?? '',
                'policy_holder' => $failedRow['policy_holder'] ?? '',
                'date_of_loss' => $failedRow['date_of_loss'] ?? '',
                'claim_registration_date' => $failedRow['claim_registration_date'] ?? '',
                'accident_number' => $failedRow['accident_number'] ?? '',
                'recovered_amount' => $failedRow['recovered_amount'] ?? '',
                'od_vehicle_vehicle_make' => $failedRow['od_vehicle_vehicle_make'] ?? '',
                'od_vehicle_vehicle_model' => $failedRow['od_vehicle_vehicle_model'] ?? '',
                'liability' => $failedRow['liability'] ?? '',
                'area' => $failedRow['area'] ?? '',
                'error' => $failedRow['error'] ?? '',
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
            'Policy Number',
            'Claim Number',
            'Sub Claim Number',
            'ID Number',
            'Mobile No',
            'Outstanding Recovery',
            'Driver Name',
            'Claim Type',
            'Claim Status',
            'Plate Number',
            'Policy Holder',
            'Date of Loss',
            'Registration Date',
            'Accident Number',
            'Recovered Amount',
            'Vehicle Make',
            'Vehicle Model',
            'Liability',
            'Area',
            'Error Reason',
        ];
    }

    /**
     * Apply styles to the worksheet
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
            'A' => 20, // Policy Number
            'B' => 20, // Claim Number
            'C' => 20, // Sub Claim Number
            'D' => 20, // ID Number
            'E' => 20, // Mobile No
            'F' => 20, // Outstanding Recovery
            'G' => 20, // Driver Name
            'H' => 15, // Claim Type
            'I' => 18, // Claim Status
            'J' => 15, // Plate Number
            'K' => 20, // Policy Holder
            'L' => 15, // Date of Loss
            'M' => 15, // Registration Date
            'N' => 20, // Accident Number
            'O' => 20, // Recovered Amount
            'P' => 20, // Vehicle Make
            'Q' => 20, // Vehicle Model
            'R' => 15, // Liability
            'S' => 15, // Area
            'T' => 50, // Error Reason
        ];
    }

}
