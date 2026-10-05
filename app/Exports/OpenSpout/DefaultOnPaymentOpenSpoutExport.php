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
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Throwable;

class DefaultOnPaymentOpenSpoutExport
{
    /**
     * @return array<string, array{heading: string, value: \Closure}>
     */
    private static function columnDefinitions(): array
    {
        return [
            'id' => [
                'heading' => __('claim.claim_id'),
                'value' => fn($item) => (string) $item->id,
            ],
            'assigned_to' => [
                'heading' => __('claim.assigned_to'),
                'value' => fn($item) => $item->assignee?->toUser?->name ?? '-',
            ],
            'claim_number' => [
                'heading' => __('claim.claim_number'),
                'value' => fn($item) => $item->claim_number ?? '-',
            ],
            'claim_status' => [
                'heading' => __('claim.status.status'),
                'value' => fn($item) => ClaimStatus::getString($item->status),
            ],
            'claim_amount' => [
                'heading' => __('claim.claim_amount'),
                'value' => fn($item) => $item->amount,
            ],
            'accident_date' => [
                'heading' => __('claim.accident_date'),
                'value' => fn($item) => self::safeDate($item, 'accident_date'),
            ],
            'intimation_date' => [
                'heading' => __('claim.intimation_date'),
                'value' => fn($item) => self::safeDate($item, 'intimation_date'),
            ],
            'insertion_date' => [
                'heading' => __('claim.submission_date'),
                'value' => fn($item) => self::safeDate($item, 'created_at'),
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
        $options = new Options();
        $options->DEFAULT_COLUMN_WIDTH = 30;
        $options->DEFAULT_ROW_HEIGHT = 20;

        $writer = new Writer($options);
        $writer->openToFile($filePath);

        $headerRow = Row::fromValues(self::headings($columns), self::headerStyle());
        $writer->addRow($headerRow);

        $queryBuilder->chunk($chunkSize, function ($claims) use ($writer, $columns, $onChunk) {
            if ($onChunk) {
                $onChunk();
            }

            foreach ($claims as $item) {
                $writer->addRow(Row::fromValues(self::mapRow($item, $columns)));
            }
        });

        $writer->close();
    }
}