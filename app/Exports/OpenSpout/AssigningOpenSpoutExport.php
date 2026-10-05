<?php

namespace App\Exports\OpenSpout;

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

class AssigningOpenSpoutExport
{
    /**
     * Column headings (localized).
     */
    public static function headings(): array
    {
        return [
            __('claim.claim_id'),
            __('claim.claim_number'),
            __('claim.supervisor'),
            __('claim.from_user'),
            __('claim.to_user'),
            __('claim.assign_date'),
            __('claim.status.status'),
        ];
    }

    /**
     * Column headings for the Summary sheet.
     */
    public static function summaryHeadings(): array
    {
        return [
            __('claim.collector_name'),
            __('claim.assigned_report.assigned_count'),
            __('claim.assigned_report.assigned_amount'),
        ];
    }

    /**
     * Map a single Assigning model to a flat row array.
     */
    public static function mapRow($item): array
    {
        return [
            (string) $item->claim?->id,
            $item->claim?->claim_number,
            $item->supervisorUser?->name,
            $item->fromUser?->name,
            $item->toUser?->name,
            self::safeDate($item, 'assign_date', 'd-m-Y H:i:s'),
            $item->is_active ? __('claim.assigned_report.active') : __('claim.assigned_report.inactive'),
        ];
    }

    private static function safeDate($model, string $attribute, string $format = 'd-m-Y H:i:s'): ?string
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
     * Build a bold style for the summary sheet's Total row.
     */
    protected static function summaryTotalStyle(): Style
    {
        return (new Style())
            ->setFontBold()
            ->setFontSize(11);
    }

    /**
     * Write the assigning history to an XLSX file (single "Assignings" sheet).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $queryBuilder
     */
    public static function write(
        $queryBuilder,
        string $filePath,
        int $chunkSize = 2000,
        ?Closure $onChunk = null
    ): void {
        self::writeInternal($queryBuilder, $filePath, null, $chunkSize, $onChunk);
    }

    /**
     * Write the assigning history with a "Summary" sheet appended.
     *
     * $summaryDetails is AssignedClaimsReportDetails::buildDetails() output —
     * one row per channel or per assignee user, top-10-capped like the chart.
     * Each row: id, name, assigned_count, assigned_amount.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $queryBuilder
     * @param  list<array<string, mixed>>  $summaryDetails
     * @param  array{total_amount: float, total_count: int}  $summaryTotals
     */
    public static function writeWithSummary(
        $queryBuilder,
        string $filePath,
        array $summaryDetails,
        array $summaryTotals,
        int $chunkSize = 2000,
        ?Closure $onChunk = null
    ): void {
        self::writeInternal($queryBuilder, $filePath, [$summaryDetails, $summaryTotals], $chunkSize, $onChunk);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $queryBuilder
     * @param  array{0: list<array<string, mixed>>, 1: array}|null  $summary  Null => single-sheet export.
     */
    private static function writeInternal($queryBuilder, string $filePath, ?array $summary, int $chunkSize, ?Closure $onChunk): void
    {
        $options = new Options();
        $options->DEFAULT_COLUMN_WIDTH = 30;
        $options->DEFAULT_ROW_HEIGHT = 20;

        $writer = new Writer($options);
        $writer->openToFile($filePath);

        if ($summary !== null) {
            $writer->getCurrentSheet()->setName('Assignings');
        }

        $writer->addRow(Row::fromValues(self::headings(), self::headerStyle()));

        $queryBuilder
            ->with(['claim', 'supervisorUser', 'fromUser', 'toUser'])
            ->chunk($chunkSize, function ($assignings) use ($writer, $onChunk) {
                $rowCount = 0;
                foreach ($assignings as $item) {
                    $writer->addRow(Row::fromValues(self::mapRow($item)));

                    if ($onChunk && ++$rowCount % 100 === 0) {
                        $onChunk();
                    }
                }

                if ($onChunk) $onChunk();
            });

        if ($summary !== null) {
            [$summaryDetails, $summaryTotals] = $summary;

            $summarySheet = $writer->addNewSheetAndMakeItCurrent();
            $summarySheet->setName('Summary');
            self::writeSummarySheet($writer, $summaryDetails, $summaryTotals);
        }

        $writer->close();
    }

    /**
     * Write the Summary sheet: one row per collector/channel, plus a Total row.
     *
     * @param  list<array<string, mixed>>  $summaryDetails
     * @param  array{total_amount: float, total_count: int}  $summaryTotals
     */
    private static function writeSummarySheet(Writer $writer, array $summaryDetails, array $summaryTotals): void
    {
        $writer->addRow(Row::fromValues(self::summaryHeadings(), self::headerStyle()));

        foreach ($summaryDetails as $row) {
            $writer->addRow(Row::fromValues([
                $row['name'] ?? '',
                (int) ($row['assigned_count'] ?? 0),
                (float) ($row['assigned_amount'] ?? 0),
            ], self::dataStyle()));
        }

        $writer->addRow(Row::fromValues([
            __('claim.status_report.total'),
            (int) ($summaryTotals['total_count'] ?? 0),
            (float) ($summaryTotals['total_amount'] ?? 0),
        ], self::summaryTotalStyle()));
    }

    protected static function dataStyle(): Style
    {
        return (new Style())
            ->setFontSize(11)
            ->setCellAlignment(\OpenSpout\Common\Entity\Style\CellAlignment::LEFT);
    }
}