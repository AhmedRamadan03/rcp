<?php

namespace App\Exports\OpenSpout;

use App\Enums\ClaimStatus;
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

class   ClaimDashboardExport
{
    /**
     * Column headings (localized).
     */
    public static function headings(): array
    {
        return [
            __('claim.claim_id'),
            __('claim.claim_number'),
            __('claim.sub_claim_number'),
            __('claim.accident_number'),
            __('claim.policy_number'),
            __('claim.plate_number'),
            __('claim.victim_plate_number'),
            __('claim.debtor_name'),
            __('claim.debtor_phone_number'),
            __('claim.debtor_id'),
            __('claim.accident_date'),
            __('claim.claim_amount'),
            __('claim.amount_after_discount'),
            __('claim.discounted_amount'),
            __('claim.paid'),
            __('claim.remaining_amount'),
            __('claim.intimation_date'),
            __('claim.submission_date'),
            __('claim.assigned_to'),
            __('claim.created_by'),
            __('claim.status.status'),
        ];
    }

    /**
     * Column headings for the Details (breakdown) sheet.
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

    private static function resolveStatusBucket($claim, float $paidAmount): string
    {
        if (in_array($claim->status, [ClaimStatus::CLOSED->value, ClaimStatus::REJECTED->value], true)) {
            return 'cleansed';
        }

        if ($paidAmount > 0) {
            return 'collected';
        }

        return 'outstanding';
    }

    /**
     * Map a single Claim model to a flat row array.
     */
    public static function mapRow($claim): array
    {
        $payment = $claim->payments?->first();
        $paidAmount = $payment?->paid_amount ?? 0;
        $claimAmount = $claim->amount ?? 0;

        $discount = $payment?->activeDiscount;
        $amountAfterDiscount = $discount?->amount_after_discount ?? $claimAmount;
        $discountedAmount = $discount?->deducted_amount ?? 0;

        return [
            (string) $claim->id,
            $claim->claim_number,
            $claim->sub_claim_number,
            $claim->accident_number,
            $claim->policy_number,
            $claim->plate_number,
            $claim->victim_plate_number,
            $claim->debtor_name,
            $claim->debtor_phone,
            $claim->debtor_nid,
            self::safeDate($claim, 'accident_date'),
            $claimAmount,
            $amountAfterDiscount,
            $discountedAmount,
            $paidAmount,
            $claimAmount - $paidAmount,
            self::safeDate($claim, 'intimation_date'),
            self::safeDate($claim, 'created_at'),
            $claim->assignees?->where('is_active', 1)?->first()?->toUser?->name,
            $claim->user?->name,
            __('claim.status.' . self::resolveStatusBucket($claim, (float) $paidAmount)),
        ];
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
     * Write claims to an XLSX file using OpenSpout (single "Claims" sheet).
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
        self::writeInternal($queryBuilder, $filePath, null, null, $chunkSize, $onChunk);
    }

    /**
     * Write claims to an XLSX file with a second "Summary" sheet appended.
     *
     * Used by the Status Report export, where $summary is the same payload the
     * dashboard chart uses (see StatusReportDetails::buildSummary()) — i.e.
     * top-10-capped exactly like the UI, not a separately computed grand total.
     * Shape: total_cleansed_count, total_cleansed_amount, total_collected_count,
     * total_collected_amount, total_outstanding_count, total_outstanding_amount,
     * total_count, total_amount.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $queryBuilder  The base query (without ->get()).
     * @param  string  $filePath  Absolute path for the output XLSX file.
     * @param  array  $summary  Summary totals for the Summary sheet.
     * @param  int  $chunkSize  Number of records loaded per database round-trip.
     */
    public static function writeWithSummary($queryBuilder, string $filePath, array $details, array $summary, int $chunkSize = 2000, ?Closure $onChunk = null): void
    {
        self::writeInternal($queryBuilder, $filePath, $details, $summary, $chunkSize, $onChunk);
    }

    /**
     * Shared writer used by write() and writeWithSummary().
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $queryBuilder
     * @param  array|null  $summary  Null => single-sheet export (legacy behavior).
     */
    private static function writeInternal($queryBuilder, string $filePath, ?array $details, ?array $summary, int $chunkSize, ?Closure $onChunk): void
    {
        $options = new Options();
        $options->DEFAULT_COLUMN_WIDTH = 30;
        $options->DEFAULT_ROW_HEIGHT  = 20;

        $writer = new Writer($options);
        $writer->openToFile($filePath);

        if ($summary !== null) {
            $writer->getCurrentSheet()->setName('Claims');
        }

        // --- Claims sheet ---
        $headerRow = Row::fromValues(self::headings(), self::headerStyle());
        $writer->addRow($headerRow);

        $queryBuilder
            ->with(['payments.activeDiscount', 'assignees.toUser', 'user'])
            ->chunk($chunkSize, function ($claims) use ($writer, $onChunk) {
                $rowCount = 0;
                foreach ($claims as $claim) {
                    $writer->addRow(Row::fromValues(self::mapRow($claim)));

                    if ($onChunk && ++$rowCount % 100 === 0) {
                        $onChunk();
                    }
                }

                if ($onChunk)
                    $onChunk();
            });

        // --- Details sheet (per-entity breakdown) ---
        if ($details !== null) {
            $detailsSheet = $writer->addNewSheetAndMakeItCurrent();
            $detailsSheet->setName('Details');
            self::writeDetailsSheet($writer, $details);
        }

        // --- Summary sheet (last) ---
        if ($summary !== null) {
            $summarySheet = $writer->addNewSheetAndMakeItCurrent();
            $summarySheet->setName('Summary');
            self::writeSummarySheet($writer, $summary);
        }

        $writer->close();
    }

    /**
     * Write the Summary sheet rows onto the writer's current sheet.
     *
     * Expects $summary shaped like StatusReportDetails::buildSummary() (the
     * same summary object the dashboard chart consumes — top-10-capped like
     * the UI): total_cleansed_count, total_cleansed_amount,
     * total_collected_count, total_collected_amount, total_outstanding_count,
     * total_outstanding_amount, total_count, total_amount.
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
            __('claim.status.cleansed'), $cleansedCount, $cleansedAmount,
        ], self::dataStyle()));

        $writer->addRow(Row::fromValues([
            __('claim.status.collected'), $collectedCount, $collectedAmount,
        ], self::dataStyle()));

        $writer->addRow(Row::fromValues([
            __('claim.status.outstanding'), $outstandingCount, $outstandingAmount,
        ], self::dataStyle()));

        $writer->addRow(Row::fromValues([
            __('claim.status_report.total'), $totalCount, $totalAmount,
        ], self::summaryTotalStyle()));
    }

    /**
     * Write the Details sheet rows onto the writer's current sheet.
     *
     * Expects each row shaped like StatusReportDetails::buildDetails() output:
     * id, name, cleansed_count, cleansed_amount, collected_count,
     * collected_amount, outstanding_count, outstanding_amount.
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

}