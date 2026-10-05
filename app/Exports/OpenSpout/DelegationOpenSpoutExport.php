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

class DelegationOpenSpoutExport
{
    /**
     * @return array<string, array{heading: string, value: \Closure}>
     */
    private static function columnDefinitions(): array
    {
        return [
            'from_user_name' => [
                'heading' => __('delegation.delegated_from'),
                'value' => fn($item) => $item->fromUserWithTrashed?->name ?? '-',
            ],
            'to_user_name' => [
                'heading' => __('delegation.delegated_to'),
                'value' => fn($item) => $item->toUserWithTrashed?->name ?? '-',
            ],
            'start_date' => [
                'heading' => __('delegation.start_date'),
                'value' => fn($item) => self::safeDate($item, 'start_date', 'Y-m-d'),
            ],
            'end_date' => [
                'heading' => __('delegation.end_date'),
                'value' => fn($item) => self::safeDate($item, 'end_date', 'Y-m-d'),
            ],
            'duration_days' => [
                'heading' => __('delegation.duration_days'),
                'value' => fn($item) => self::duration($item),
            ],
            'status' => [
                'heading' => __('delegation.status'),
                'value' => fn($item) => $item->status,
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

    private static function duration($item): string
    {
        if (!$item->start_date) {
            return '0';
        }

        $start = Carbon::parse($item->start_date);
        $end = $item->end_date ? Carbon::parse($item->end_date) : Carbon::now();

        return (string) $start->diffInDays($end);
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

        $queryBuilder->chunk($chunkSize, function ($delegations) use ($writer, $columns, $onChunk) {
            if ($onChunk) {
                $onChunk();
            }

            foreach ($delegations as $item) {
                $writer->addRow(Row::fromValues(self::mapRow($item, $columns)));
            }
        });

        $writer->close();
    }
}