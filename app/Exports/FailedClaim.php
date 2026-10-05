<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FailedClaim implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    public function __construct(
        public $failedClaims,
    ) {}

    /**
     * Return the collection of failed claims data.
     *
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return collect($this->failedClaims)->map(function ($claim) {
            return [
                'policy_number' => $claim['policy_number'] ?? '',
                'claim_number' => $claim['claim_number'] ?? '',
                'sub_claim_number' => $claim['sub_claim_number'] ?? '',
                'debtor_id' => $claim['debtor_id'] ?? '',
                'debtor_phone_number' => $claim['debtor_phone_number'] ?? '',
                'claim_amount' => $claim['claim_amount'] ?? '',
                'debtor_name' => $claim['debtor_name'] ?? '',
                'claim_type' => $claim['claim_type'] ?? '',
                'debtor_type' => $claim['debtor_type'] ?? '',
                'plate_number' => $claim['plate_number'] ?? '',
                'intimation_date' => $this->formatExcelDate($claim['intimation_date'] ?? ''),
                'accident_date' => $this->formatExcelDate($claim['accident_date'] ?? ''),
                'accident_number' => $claim['accident_number'] ?? '',
                'recovered_amount' => $claim['recovered_amount'] ?? '',
                'vehicle_make' => $claim['vehicle_make'] ?? '',
                'vehicle_model' => $claim['vehicle_model'] ?? '',
                'mistake_percentage' => $claim['mistake_percentage'] ?? '',
                'city' => $claim['city'] ?? '',
                'remarks' => $claim['remarks'] ?? '',
                'employee_email' => $claim['employee_email'] ?? '',
                'claim_status' => $claim['claim_status'] ?? '',
                'error_message' => $claim['error_message'] ?? '',
                'row_number' => $claim['row_number'] ?? '',
            ];
        });
    }

    /**
     * Format Excel serial date to readable date format.
     *
     * @param mixed $value
     * @return string
     */
    private function formatExcelDate($value): string
    {
        if (empty($value)) {
            return '';
        }

        // If it's already a formatted date string, return it
        if (is_string($value) && !is_numeric($value)) {
            return $value;
        }

        // If it's an Excel serial number, convert it
        if (is_numeric($value)) {
            try {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
                return $date->format('Y-m-d');
            } catch (\Exception $e) {
                return $value;
            }
        }

        return $value;
    }

    /**
     * Define the headings for the Excel file.
     *
     * @return array
     */
    public function headings(): array
    {
        return [
            'Policy Number',
            'Claim Number',
            'Sub Claim Number',
            'Debtor ID',
            'Debtor phone number',
            'Claim Amount',
            'Debtor Name',
            'Claim Type',
            'Debtor Type',
            'Plate Number',
            'Intimation Date',
            'Accident Date',
            'Accident Number',
            'Recovered Amount',
            'Vehicle Make',
            'Vehicle Model',
            'Mistake percentage',
            'City',
            'Remarks',
            'Employee Email',
            'Claim Status',
            'Error Message',
            'Row Number',
        ];
    }

    /**
     * Apply styles to the worksheet.
     *
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row (header row)
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FF000000'],
                ],
                'alignment' => [
                    'horizontal' => 'center',
                    'vertical' => 'center',
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['argb' => 'FFAAAAAA'],
                ],
            ],
        ];
    }
}
