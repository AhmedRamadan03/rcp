<?php

namespace App\Exports\OpenSpout;


use Closure;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

class ClientRequestOpenSpoutExport
{
    /**
     * @return array<string, array{heading: string, value: \Closure}>
     */
    private static function columnDefinitions(): array
    {
        return [
            'claim_id' => [
                'heading' => __('claim.claim_id'),
                'value' => fn($item) => $item->claim_id,
            ],
            'claim_number' => [
                'heading' => __('claim.claim_number'),
                'value' => fn($item) => $item->claim?->claim_number,
            ],
            'reason' => [
                'heading' => __('client-request.reason'),
                'value' => fn($item) => $item->reason,
            ],
            'status' => [
                'heading' => __('client-request.status'),
                'value' => fn($item) => $item->status,
            ],
            'approved_by' => [
                'heading' => __('client-request.approved_by'),
                'value' => fn($item) => $item->approvedBy?->name,
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
