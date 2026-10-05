<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AuthLogExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
{
    protected $authLogs;

    public function __construct($authLogs)
    {
        $this->authLogs = $authLogs;
    }

    public function collection()
    {
        return $this->authLogs->map(function ($authLog) {
            return [
                'user_name'  => $authLog->authenticatable->name ?? null,
                'user_email' => $authLog->authenticatable->email ?? null,
                'ip_address' => $authLog->ip_address ?? null,
                'user_agent' => $authLog->user_agent ?? null,
                'login_at' => $authLog->login_at ? Carbon::parse($authLog->login_at)->format('Y-m-d H:i:s') : null,
                'login_status' => $authLog->login_successful ? 'True' : 'False',
                'logout_at' => $authLog->logout_at ? Carbon::parse($authLog->logout_at)->format('Y-m-d H:i:s') : null,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'User Name',
            'User Email',
            'Ip Address',
            'User Agent',
            'Login At',
            'Login Status',
            'Logout At',
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
        ];
    }
}