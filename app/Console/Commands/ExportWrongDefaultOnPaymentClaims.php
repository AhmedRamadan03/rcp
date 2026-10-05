<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class ExportWrongDefaultOnPaymentClaims extends Command
{
    protected $signature = 'app:export-wrong-default-on-payment-claims
        {--output= : Output xlsx path. Defaults to storage/app/wrong-default-on-payment-claims-{timestamp}.xlsx}';

    protected $description = 'Export claims wrongly converted to default_in_payment_by_system from a finished status';

    public function handle(): int
    {
        $rows = WrongDefaultOnPaymentClaims::rows();
        $path = $this->resolveOutputPath();

        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->error("Could not create output directory: {$directory}");

            return self::FAILURE;
        }

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['claim_id', 'previous_status', 'conversion_count', 'conversion_date']));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues([
                $row->claim_id,
                $row->previous_status,
                $row->conversion_count,
                $row->conversion_date,
            ]));
        }

        $writer->close();

        $this->info("Exported {$rows->count()} claim(s) to {$path}");

        return self::SUCCESS;
    }

    private function resolveOutputPath(): string
    {
        $output = $this->option('output');

        if (filled($output)) {
            return $output;
        }

        return storage_path('app/wrong-default-on-payment-claims-'.now()->format('Y-m-d_His').'.xlsx');
    }
}
