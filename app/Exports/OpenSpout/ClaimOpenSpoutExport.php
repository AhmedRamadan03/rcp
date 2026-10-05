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

class   ClaimOpenSpoutExport
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
     * Map a single Claim model to a flat row array.
     */
    public static function mapRow($claim): array
    {
        $paidAmount = $claim->payments?->first()?->paid_amount ?? 0;

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
            $claim->amount,
            $paidAmount,
            $claim->amount - $paidAmount,
            self::safeDate($claim, 'intimation_date'),
            self::safeDate($claim, 'created_at'),
            $claim->assignees?->where('is_active', 1)?->first()?->toUser?->name,
            $claim->user?->name,
            ClaimStatus::getString($claim->status),
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
            ->with(['payments', 'assignees.toUser', 'user'])
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
