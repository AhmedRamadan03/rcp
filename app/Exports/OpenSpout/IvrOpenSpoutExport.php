<?php

namespace App\Exports\OpenSpout;


use Carbon\Carbon;
use Closure;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Writer\XLSX\Options;
use Throwable;

class IvrOpenSpoutExport
{
    /**
     * تعريف جميع الأعمدة المتاحة برمجياً مع ترجماتها.
     */
    public static function availableColumns(): array
    {
        return [
            'claim_number'       => __('claim.claim_number'),
            'phone_number'       => __('claim.debtor_phone_number'),
            'call_date'      => __('claim.call_date'),
            'call_status'        => __('claim.call_status'),
            'call_duration'  => __('claim.call_duration'),
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
            'claim_number'       => $item->paymentLink?->payment?->claim?->claim_number,
            'phone_number'       => $item->paymentLink?->payment?->claim?->debtor_phone,
            'call_date'    => self::safeDate($item, 'created_at'),
            'call_status'        => $item->status,
            'call_duration'  => $item->duration,
        ];
    }

    /**
     * Map a single ivr model to a flat row array.
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

    /**
     * Safely format a date attribute, returning the raw value when parsing fails.
     */
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
     * Write ivrs to an XLSX file using OpenSpout.
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
        $options->DEFAULT_ROW_HEIGHT = 20;

        // Override width for the Body column (c) phone number
        $options->setColumnWidth(50, 3);

        $writer = new Writer($options);
        $writer->openToFile($filePath);

        // --- Header row ---
         $dynamicHeadings = self::headings($userColumnsOrder);
        $headerRow = Row::fromValues($dynamicHeadings, self::headerStyle());
        $writer->addRow($headerRow);

        // --- Data rows (chunked to keep memory flat) ---
        $queryBuilder
            ->with(['paymentLink.payment.claim'])
            ->chunk($chunkSize, function ($ivrs) use ($writer, $onChunk, $userColumnsOrder) {
                if ($onChunk) $onChunk();
                foreach ($ivrs as $item) {
                    $writer->addRow(Row::fromValues(self::mapRow($item, $userColumnsOrder)));
                }
            });

        $writer->close();
    }
}
