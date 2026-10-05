<?php

namespace App\Exports\OpenSpout;

use App\Helpers\PaymentTransactionResponseParser;
use Carbon\Carbon;
use Closure;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Throwable;

class PaymentTransactionOpenSpoutExport
{
    /**
     * @return array<string, array{heading: string, value: \Closure}>
     */
    private static function columnDefinitions(): array
    {
        return [
            'claim_id' => [
                'heading' => __('claim.claim_id'),
                'value' => fn($item) => $item->link?->payment->claim_id,
            ],
            'amount' => [
                'heading' => __('transaction.amount'),
                'value' => fn($item) => $item->amount,
            ],
            'collector_name' => [
                'heading' => __('transaction.collector_name'),
                'value' => fn($item) => $item->transactionCollector?->name,
            ],
            'claim_number' => [
                'heading' => __('transaction.claim_number'),
                'value' => fn($item) => $item->link?->payment?->claim?->claim_number,
            ],
            'installment_number' => [
                'heading' => __('transaction.installment_number'),
                'value' => fn($item) => $item->link?->order,
            ],
            'created_at' => [
                'heading' => __('transaction.date'),
                'value' => fn($item) => self::safeDate($item, 'created_at', 'Y-m-d H:i:s'),
            ],
            'status' => [
                'heading' => __('transaction.result'),
                'value' => fn($item) => $item->send_status,
            ],
            'payment_type' => [
                'heading' => __('transaction.payment_method'),
                'value' => fn($item) => $item->payment_type,
            ],
            'rrn' => [
                'heading' => __('transaction.rrn'),
                'value' => fn($item) => PaymentTransactionResponseParser::RRN($item),
            ],
            'payment_id' => [
                'heading' => __('claim.payment_id'),
                'value' => fn($item) => PaymentTransactionResponseParser::paymentId($item),
            ],
            'card_brand' => [
                'heading' => __('claim.card_brand'),
                'value' => fn($item) => PaymentTransactionResponseParser::cardBrand($item),
            ],
            'masked_pan' => [
                'heading' => __('transaction.masked_pan'),
                'value' => fn($item) => PaymentTransactionResponseParser::maskedPan($item),
            ],
        ];
    }

    public static function defaultColumns(): array
    {
        return array_keys(self::columnDefinitions());
    }

    /**
     * @param  array<string>|null  $columns
     */
    public static function headings(?array $columns = null): array
    {
        $definitions = self::columnDefinitions();
        $activeColumns = $columns ?? self::defaultColumns();

        return array_map(
            fn(string $key) => $definitions[$key]['heading'],
            $activeColumns
        );
    }

    /**
     * Column headings for the Details (per-collector breakdown) sheet.
     */
    public static function detailsHeadings(): array
    {
        return [
            __('claim.collector_name'),
            __('claim.status_report.cleansed_count'),
            __('claim.status_report.cleansed_amount'),
            __('claim.status_report.collected_count'),
            __('claim.status_report.collected_amount'),
            __('claim.status_report.outstanding_count'),
            __('claim.status_report.outstanding_amount'),
        ];
    }

    /**
     * Column headings for the Summary sheet.
     */
    public static function summaryHeadings(): array
    {
        return [
            __('claim.status_report.type'),
            __('claim.status_report.count'),
            __('claim.status_report.amount'),
        ];
    }

    /**
     * @param  array<string>|null  $columns
     */
    public static function mapRow($item, ?array $columns = null): array
    {
        $definitions = self::columnDefinitions();
        $activeColumns = $columns ?? self::defaultColumns();

        return array_map(
            fn(string $key) => ($definitions[$key]['value'])($item),
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
     * Build a bold style for the summary sheet's Total row.
     */
    protected static function summaryTotalStyle(): Style
    {
        return (new Style())
            ->setFontBold()
            ->setFontSize(11);
    }

    /**
     * Write transactions to an XLSX file (single "Transactions" sheet).
     * Used elsewhere — signature and behavior unchanged.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $queryBuilder
     * @param  array<string>|null  $columns
     */
    public static function write(
        $queryBuilder,
        string $filePath,
        ?array $columns = null,
        int $chunkSize = 2000,
        ?Closure $onChunk = null
    ): void {
        self::writeInternal($queryBuilder, $filePath, $columns, null, null, $chunkSize, $onChunk);
    }

    /**
     * Write transactions to an XLSX file with "Details" (per-collector
     * breakdown) and "Summary" sheets appended, in that order.
     *
     * $details mirrors the Status Report response's `details` array — one
     * row per collector/user, already top-10-capped like the dashboard.
     * Each row: id, name, cleansed_count, cleansed_amount, collected_count,
     * collected_amount, outstanding_count, outstanding_amount.
     *
     * $summary mirrors the response's `summary` object: total_cleansed_count,
     * total_cleansed_amount, total_collected_count, total_collected_amount,
     * total_outstanding_count, total_outstanding_amount, total_count, total_amount.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $queryBuilder
     * @param  array<string>|null  $columns
     * @param  list<array<string, mixed>>  $details
     * @param  array  $summary
     */
    public static function writeWithSummary(
        $queryBuilder,
        string $filePath,
        ?array $columns,
        array $details,
        array $summary,
        int $chunkSize = 2000,
        ?Closure $onChunk = null
    ): void {
        self::writeInternal($queryBuilder, $filePath, $columns, $details, $summary, $chunkSize, $onChunk);
    }

    /**
     * Shared writer used by write() and writeWithSummary().
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $queryBuilder
     * @param  array<string>|null  $columns
     * @param  list<array<string, mixed>>|null  $details  Null => single-sheet export (legacy behavior).
     * @param  array|null  $summary  Null => single-sheet export (legacy behavior).
     */
    private static function writeInternal($queryBuilder, string $filePath, ?array $columns, ?array $details, ?array $summary, int $chunkSize, ?Closure $onChunk): void
    {
        $options = new Options();
        $options->DEFAULT_COLUMN_WIDTH = 30;
        $options->DEFAULT_ROW_HEIGHT = 20;

        $writer = new Writer($options);
        $writer->openToFile($filePath);

        if ($summary !== null) {
            $writer->getCurrentSheet()->setName('Transactions');
        }

        $headerRow = Row::fromValues(self::headings($columns), self::headerStyle());
        $writer->addRow($headerRow);

        $queryBuilder->chunk($chunkSize, function ($transactions) use ($writer, $columns, $onChunk) {
            if ($onChunk) {
                $onChunk();
            }

            foreach ($transactions as $item) {
                $writer->addRow(Row::fromValues(self::mapRow($item, $columns)));
            }
        });

        if ($details !== null) {
            $detailsSheet = $writer->addNewSheetAndMakeItCurrent();
            $detailsSheet->setName('Details');
            self::writeDetailsSheet($writer, $details);
        }

        if ($summary !== null) {
            $summarySheet = $writer->addNewSheetAndMakeItCurrent();
            $summarySheet->setName('Summary');
            self::writeSummarySheet($writer, $summary);
        }

        $writer->close();
    }

    /**
     * Write the Details sheet: one row per collector/user.
     *
     * @param  list<array<string, mixed>>  $details
     */
    private static function writeDetailsSheet(Writer $writer, array $details): void
    {
        $writer->addRow(Row::fromValues(self::detailsHeadings(), self::headerStyle()));

        foreach ($details as $row) {
            $writer->addRow(Row::fromValues([
                $row['name'] ?? '',
                (int) ($row['cleansed_count'] ?? 0),
                (float) ($row['cleansed_amount'] ?? 0),
                (int) ($row['collected_count'] ?? 0),
                (float) ($row['collected_amount'] ?? 0),
                (int) ($row['outstanding_count'] ?? 0),
                (float) ($row['outstanding_amount'] ?? 0),
            ], self::dataStyle()));
        }
    }

    /**
     * Write the Summary sheet rows onto the writer's current sheet.
     */
    private static function writeSummarySheet(Writer $writer, array $summary): void
    {
        $cleansedCount = (int) ($summary['total_cleansed_count'] ?? 0);
        $cleansedAmount = (float) ($summary['total_cleansed_amount'] ?? 0);
        $collectedCount = (int) ($summary['total_collected_count'] ?? 0);
        $collectedAmount = (float) ($summary['total_collected_amount'] ?? 0);
        $outstandingCount = (int) ($summary['total_outstanding_count'] ?? 0);
        $outstandingAmount = (float) ($summary['total_outstanding_amount'] ?? 0);
        $totalCount = (int) ($summary['total_count'] ?? ($cleansedCount + $collectedCount + $outstandingCount));
        $totalAmount = (float) ($summary['total_amount'] ?? round($cleansedAmount + $collectedAmount + $outstandingAmount, 2));

        $writer->addRow(Row::fromValues(self::summaryHeadings(), self::headerStyle()));

        $writer->addRow(Row::fromValues([
            __('claim.status.cleansed'),
            $cleansedCount,
            $cleansedAmount,
        ], self::dataStyle()));

        $writer->addRow(Row::fromValues([
            __('claim.status.collected'),
            $collectedCount,
            $collectedAmount,
        ], self::dataStyle()));

        $writer->addRow(Row::fromValues([
            __('claim.status.outstanding'),
            $outstandingCount,
            $outstandingAmount,
        ], self::dataStyle()));

        $writer->addRow(Row::fromValues([
            __('claim.status_report.total'),
            $totalCount,
            $totalAmount,
        ], self::summaryTotalStyle()));
    }
}