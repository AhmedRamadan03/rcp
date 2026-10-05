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

class AssignedClaimsOpenSpoutExport
{
    
    /**
     * تعريف جميع الأعمدة المتاحة برمجياً مع ترجماتها.
     */
    public static function availableColumns(): array
    {
        return [
            'claim_number'       => __('claim.claim_number'),
            'supervisor'       => __('claim.supervisor'),
            'from_user'      => __('claim.from_user'),
            'to_user'        => __('claim.to_user'),
            'assign_date'  => __('claim.assign_date'),
            'status'  => __('claim.assign_status'),
            
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
            'claim_number'     => $item->claim?->claim_number,
            'supervisor'       => $item->supervisorUser?->name,
            'from_user'        => $item->fromUser?->name,
            'to_user'          => $item->toUser?->name,
            'assign_date'      => self::safeDate($item, 'assign_date', 'd-m-Y H:i:s'),
            'status'          => $item->is_active ? 'Active' : 'Inactive',
        
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
     * Write messages to an XLSX file using OpenSpout.
     *
     * Accepts an Eloquent Builder so it can stream results via chunk()
     * to keep memory usage constant regardless of dataset size.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $queryBuilder  The base query (without ->get()).
     * @param  string  $filePath  Absolute path for the output XLSX file.
     * @param  int  $chunkSize  Number of records loaded per database round-trip.
     */
    public static function write($queryBuilder, string $filePath, int $chunkSize = 2000, ?Closure $onChunk = null, array $userColumnsOrder = []): void
    {
        $options = new Options();
        $options->DEFAULT_COLUMN_WIDTH = 30;
        $options->DEFAULT_ROW_HEIGHT  = 20;

        // Override width for the Body column (A)
        $options->setColumnWidth(50, 1);

        $writer = new Writer($options);
        $writer->openToFile($filePath);

         // --- Header row ---
        $dynamicHeadings = self::headings($userColumnsOrder);
        $headerRow = Row::fromValues($dynamicHeadings, self::headerStyle());
        $writer->addRow($headerRow);

        // --- Data rows (chunked to keep memory flat) ---
        $queryBuilder
            ->chunk($chunkSize, function ($data) use ($writer, $onChunk, $userColumnsOrder) {
                if ($onChunk) {
                    ($onChunk)();   // ✅ يشتغل على PHP 7.4+
                }

                foreach ($data as $msg) {
                    $writer->addRow(Row::fromValues(self::mapRow($msg, $userColumnsOrder)));
                }
            });

        $writer->close();
    }
}
