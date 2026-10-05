<?php

namespace App\Exports\OpenSpout;

use App\Enums\ClaimStatus;
use App\Enums\DataHistoryEnum;
use App\Models\DataHistory;
use Carbon\Carbon;
use Closure;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Writer\XLSX\Options;
use Throwable;

class   TargetOpenSpoutExport
{
    /**
     * Column headings (localized).
     */
    public static function headings(): array
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
     * Map a single Claim model to a flat row array.
     */
    public static function mapRow($collector): array
    {
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
    }

   

    /**
     * Build a styled header row.
     */
    protected static function headerStyle(): Style
    {
        $border = new Border(
            new BorderPart(Border::BOTTOM, Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID),
        );

        return (new Style())
            ->setFontBold()
            ->setFontSize(11)
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor('5A56E3')
            ->setBorder($border);
    }


    /**
     * Build a style for data rows to ensure left alignment.
     */
    protected static function dataStyle(): Style
    {
        return (new Style())
            ->setFontSize(11)
            ->setCellAlignment(CellAlignment::LEFT);
    }

    /**
     * Write claims to an XLSX file using OpenSpout.
     *
     * Accepts an Eloquent Builder so it can stream results via chunk()
     * to keep memory usage constant regardless of dataset size.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $queryBuilder  The base query (without ->get()).
     * @param  string  $filePath  Absolute path for the output XLSX file.
     * @param  int  $chunkSize  Number of records loaded per database round-trip.
     */
    public static function write($queryBuilder, string $filePath, int $chunkSize = 2000, ?Closure $onChunk = null): void
    {
        $options = new Options();
        $options->DEFAULT_COLUMN_WIDTH = 30;
        $options->DEFAULT_ROW_HEIGHT  = 20;

        $writer = new Writer($options);
        $writer->openToFile($filePath);

        // --- Header row ---
        $headerRow = Row::fromValues(self::headings(), self::headerStyle());
        $writer->addRow($headerRow);

        // --- Data rows (chunked to keep memory flat) ---
        $queryBuilder
            ->chunk($chunkSize, function ($claims) use ($writer, $onChunk) {
                $rowCount = 0;
                foreach ($claims as $claim) {
                    $writer->addRow(Row::fromValues(self::mapRow($claim)));

                    // check every 100 rows instead of every chunk
                    if ($onChunk && ++$rowCount % 100 === 0) {
                        $onChunk();
                    }
                }

                // check at end of chunk too
                if ($onChunk) $onChunk();
            });

        $writer->close();
    }
}
