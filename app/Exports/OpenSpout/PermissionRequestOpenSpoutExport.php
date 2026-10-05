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

class PermissionRequestOpenSpoutExport
{
    /**
     * @return array<string, array{heading: string, value: \Closure}>
     */
    private static function columnDefinitions(): array
    {
        return [
            'claim_id' => [
                'heading' => __('claim.claim_id'),
                'value' => fn ($item) => $item->claim_id,
            ],
            'claim_number' => [
                'heading' => __('claim.claim_number'),
                'value' => fn ($item) => $item->claim?->claim_number,
            ],
            'request_by' => [
                'heading' => __('request.request_by'),
                'value' => fn ($item) => $item->user?->name,
            ],
            'from_status' => [
                'heading' => __('request.current_status'),
                'value' => fn ($item) => $item->from_status,
            ],
            'to_status' => [
                'heading' => __('request.new_status'),
                'value' => fn ($item) => $item->to_status,
            ],
            'note' => [
                'heading' => __('request.reason'),
                'value' => fn ($item) => $item->note,
            ],
            'created_at' => [
                'heading' => __('request.requested_at'),
                'value' => fn ($item) => self::safeDate($item, 'created_at', 'Y-m-d H:i:s'),
            ],
            'status' => [
                'heading' => __('request.status'),
                'value' => fn ($item) => $item->request_status,
            ],
            'supervisor_name' => [
                'heading' => __('request.action_took_by'),
                'value' => fn ($item) => $item->supervisor?->name,
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
            fn (string $key) => $definitions[$key]['heading'],
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
            fn (string $key) => ($definitions[$key]['value'])($item),
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

        $queryBuilder
            ->with(['claim', 'user', 'supervisor'])
            ->chunk($chunkSize, function ($requests) use ($writer, $columns, $onChunk) {
                if ($onChunk) {
                    $onChunk();
                }

                foreach ($requests as $item) {
                    $writer->addRow(Row::fromValues(self::mapRow($item, $columns)));
                }
            });

        $writer->close();
    }
}
