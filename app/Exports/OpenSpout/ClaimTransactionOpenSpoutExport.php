<?php

namespace App\Exports\OpenSpout;

use App\Helpers\PaymentTransactionResponseParser;
use Carbon\Carbon;
use Closure;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Throwable;

class ClaimTransactionOpenSpoutExport
{
    public static function availableColumns(): array
    {
        return [
            'payment_id' => __('claim.payment_id'),
            'card_brand' => __('claim.card_brand'),
            'masked_pan' => __('claim.masked_pan'),
            'payment_type' => __('claim.payment_type'),
            'rrn' => __('transaction.rrn'),
            'date' => __('transaction.date'),
            'status' => __('claim.result'),
        ];
    }

    public static function defaultColumns(): array
    {
        return array_keys(self::availableColumns());
    }

    public static function headings(array $userColumnsOrder = []): array
    {
        $allColumns = self::availableColumns();
        $activeColumns = $userColumnsOrder ?: self::defaultColumns();

        return array_values(array_filter(array_map(
            fn (string $key) => $allColumns[$key] ?? null,
            $activeColumns
        )));
    }

    private static function rawRowData($transaction): array
    {
        return [
            'payment_id' => PaymentTransactionResponseParser::paymentId($transaction),
            'card_brand' => PaymentTransactionResponseParser::cardBrand($transaction),
            'masked_pan' => PaymentTransactionResponseParser::maskedPan($transaction),
            'payment_type' => $transaction->payment_type,
            'rrn' => PaymentTransactionResponseParser::RRN($transaction),
            'date' => self::safeDate($transaction, 'created_at', 'Y-m-d H:i:s'),
            'status' => $transaction->send_status,
        ];
    }

    public static function mapRow($transaction, array $userColumnsOrder = []): array
    {
        $rawData = self::rawRowData($transaction);
        $activeColumns = $userColumnsOrder ?: self::defaultColumns();

        return array_map(
            fn (string $key) => $rawData[$key] ?? null,
            $activeColumns
        );
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

    public static function write(
        $queryBuilder,
        string $filePath,
        int $chunkSize = 2000,
        ?Closure $onChunk = null,
        array $userColumnsOrder = []
    ): void {
        $options = new Options();
        $options->DEFAULT_COLUMN_WIDTH = 30;
        $options->DEFAULT_ROW_HEIGHT = 20;

        $writer = new Writer($options);
        $writer->openToFile($filePath);

        $headerRow = Row::fromValues(self::headings($userColumnsOrder), self::headerStyle());
        $writer->addRow($headerRow);

        $queryBuilder->chunk($chunkSize, function ($transactions) use ($writer, $onChunk, $userColumnsOrder) {
            if ($onChunk) {
                $onChunk();
            }

            foreach ($transactions as $transaction) {
                $writer->addRow(Row::fromValues(self::mapRow($transaction, $userColumnsOrder)));
            }
        });

        $writer->close();
    }
}
