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

class CollectorTargetsOpenSpoutExport
{

    /**
     * تعريف جميع الأعمدة المتاحة برمجياً مع ترجماتها.
     */
    public static function availableColumns(): array
    {
        return [
            'target'           => __('target.target'),
            'arcieved_target'   => __('target.arcieved_target'),
            'remaining_target'  => __('target.remaining_target'),
            'start_date'      => __('target.start_date'),
            'end_date'      => __('target.end_date'),
            'status'      => __('target.status'),
        ];
    }
   
     /**
     * توليد العناوين ديناميكياً بناءً على الترتيب المختار من المستخدم.
     */
    public static function headings(array $userColumnsOrder): array
    {
        $allColumns = self::availableColumns();
        $headings = [];

        foreach ($userColumnsOrder as $key) {
            if (isset($allColumns[$key])) {
                $headings[] = $allColumns[$key];
            }
        }

        // إذا كانت مصفوفة المستخدم فارغة، نرجع كل الأعمدة كحالة افتراضية
        return empty($headings) ? array_values($allColumns) : $headings;
    }

     private static function rawRowData($item): array
    {

        return [
            'target'           => $item->target,
            'arcieved_target'   => $item->achieved,
            'remaining_target'  => $item->remaining,
            'start_date'      => self::safeDate($item, 'start_date','d-m-Y H:i:s'),
            'end_date'      => self::safeDate($item, 'end_date','d-m-Y H:i:s'),
            'status'      => $item->status,
        ];
    }

    /**
     * Map a single Claim model to a flat row array.
     */
    public static function mapRow($item, array $userColumnsOrder): array
    {
        $rawData = self::rawRowData($item);
        
        // If no column order is specified, return all data in default order
        if (empty($userColumnsOrder)) {
            return array_values($rawData);
        }

        $mappedRow = [];
        foreach ($userColumnsOrder as $key) {
            if (array_key_exists($key, $rawData)) {
                $mappedRow[] = $rawData[$key];
            }
        }

        return $mappedRow;
    }

   private static function safeDate($model, string $attribute, string $format = 'd-m-Y'): ?string
    {
        try {
            $value = $model->{$attribute};
            return $value ? Carbon::parse($value)->format($format) : null;
        } catch (Throwable) {
            return $model->getRawOriginal($attribute);
        }
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
     * @param  \Closure|null  $onChunk  Callback function to execute on each chunk.
     * @param  array  $userColumnsOrder  Order of columns to display.
     */
    public static function write($queryBuilder, string $filePath, int $chunkSize = 2000, ?Closure $onChunk = null, array $userColumnsOrder = []): void
    {
        $options = new Options();
        $options->DEFAULT_COLUMN_WIDTH = 30;
        $options->DEFAULT_ROW_HEIGHT  = 20;

        $writer = new Writer($options);
        $writer->openToFile($filePath);

        // --- Header row ---
         $dynamicHeadings = self::headings($userColumnsOrder);
        $headerRow = Row::fromValues($dynamicHeadings, self::headerStyle());
        $writer->addRow($headerRow);

        // --- Data rows (chunked to keep memory flat) ---
        $queryBuilder
            ->chunk($chunkSize, function ($claims) use ($writer, $onChunk, $userColumnsOrder) {
                $rowCount = 0;
                foreach ($claims as $claim) {
                    $writer->addRow(Row::fromValues(self::mapRow($claim, $userColumnsOrder)));

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
