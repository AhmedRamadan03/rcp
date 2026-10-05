<?php

namespace App\Exports\OpenSpout;

use App\Enums\ClaimStatus;
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

class AccountingOpenSpoutExport
{
    /**
     * تعريف جميع الأعمدة المتاحة برمجياً مع ترجماتها.
     */
    public static function availableColumns(): array
    {
        return [
            'claim_id'           => __('claim.claim_id'),
            'claim_number'       => __('claim.claim_number'),
            'claim_amount'       => __('claim.claim_amount'),
            'invoiced_status'      => __('claim.invoiced_status'),
            'invoiced_date'        => __('claim.invoiced_date'),
            'claim_creation_date'  => __('claim.claim_creation_date'),
            'user_type'            => __('claim.user_type'),
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

    /**
     * جلب مصفوفة البيانات كاملة لكل الحقول المتاحة.
     */
    private static function rawRowData($claim): array
    {

        return [
            'claim_id'           => (string) $claim->id,
            'claim_number'       => $claim->claim_number,
            'claim_amount'       => $claim->amount,
            'invoiced_status'    => $claim->invoice_status,
            'invoiced_date'      => self::safeDate($claim, 'invoice_date'),
            'claim_creation_date' => self::safeDate($claim, 'created_at'),
            'user_type'          => __('claim.' . $claim?->assignee?->toUser?->user_type) ,
        ];
    }

    /**
     * فرز وترتيب بيانات الصف بناءً على رغبة المستخدم.
     */
    public static function mapRow($claim, array $userColumnsOrder): array
    {
        $rawData = self::rawRowData($claim);
        
        // إذا لم يحدد المستخدم ترتيباً، نرجع البيانات بالترتيب الافتراضي كاملة
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
     * Safely format a date attribute.
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
     * Write claims to an XLSX file using OpenSpout.
     * 
     * @param array $userColumnsOrder مصفوفة تحتوي على مفاتيح الأعمدة بالترتيب المختار (مثال: ['claim_id', 'debtor_name'])
     */
    public static function write($queryBuilder, string $filePath, int $chunkSize = 2000, ?Closure $onChunk = null, array $userColumnsOrder = []): void
    {
        $options = new Options(); 
        $options->DEFAULT_COLUMN_WIDTH = 30;
        $options->DEFAULT_ROW_HEIGHT  = 20;

        $writer = new Writer($options);
        $writer->openToFile($filePath);

        // --- توليد العناوين بناءً على ترتيب المستخدم ---
        $dynamicHeadings = self::headings($userColumnsOrder);
        $headerRow = Row::fromValues($dynamicHeadings, self::headerStyle());
        $writer->addRow($headerRow);

        // --- طباعة الصفوف بالترتيب الديناميكي ---
        $queryBuilder
            ->with(['assignee.toUser','user'])
            ->chunk($chunkSize, function ($claims) use ($writer, $onChunk, $userColumnsOrder) {
                $rowCount = 0;
                foreach ($claims as $claim) {
                    // تمرير مصفوفة الترتيب لدالة mapRow
                    $rowData = self::mapRow($claim, $userColumnsOrder);
                    $writer->addRow(Row::fromValues($rowData));

                    if ($onChunk && ++$rowCount % 100 === 0) {
                        $onChunk();
                    }
                }

                if ($onChunk) $onChunk();
            });

        $writer->close();
    }
}