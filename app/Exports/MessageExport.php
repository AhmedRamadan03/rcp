<?php
namespace App\Exports;

use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class MessageExport implements 
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
        return Message::filter()
            ->with(['claim:id,claim_number,sub_claim_number'])
            ->select([
                'id',
                'claim_id',
                'phone_number',
                'send_date',
                'status',
                'message_status',
                'status_message',
                'sent_count',
                'body'
            ]);
    }

    public function map($msg): array
    {
        return [
            $msg->messageWithUrl,
            $msg->claim?->claim_number,
            $msg->claim?->sub_claim_number,
            $msg->phone_number,
            $msg->send_date,
            $msg->status,
            $msg->message_status,
            $msg->status_message,
            $msg->sent_count,
        ];
    }
 public function chunkSize(): int
    {
        return 1000; // يقرأ كل مرة 1000 صف بس
    }
    
    public function headings(): array
    {
        return [
            "Body",
            "Claim Number",
            "Sub Claim Number",
            "Phone Number",
            "Send Date",
            "Status",
            "Message Status",
            "Status Message",
            "Sent Count",
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
            'G' => 30,
            'H' => 30,
            'I' => 30,
        ];
    }
}