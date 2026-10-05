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

class AuthenticationLogOpenSpoutExport
{
    /**
     * @return array<string, array{heading: string, value: \Closure}>
     */
    private static function columnDefinitions(): array
    {
        return [
            'name' => [
                'heading' => __('auth_log.user_name'),
                'value' => fn($item) => $item->authenticatable?->name ?? '-',
            ],
            'email' => [
                'heading' => __('auth_log.user_email'),
                'value' => fn($item) => $item->authenticatable?->email ?? '-',
            ],
            'ip_address' => [
                'heading' => __('auth_log.ip_address'),
                'value' => fn($item) => $item->ip_address ?? '-',
            ],
            'user_agent' => [
                'heading' => __('auth_log.user_agent'),
                'value' => fn($item) => $item->user_agent ?? '-',
            ],
            'login_at' => [
                'heading' => __('auth_log.login_at'),
                'value' => fn($item) => self::safeDate($item, 'login_at', 'Y-m-d H:i:s'),
            ],
            'login_status' => [
                'heading' => __('auth_log.login_status'),
                'value' => fn($item) => $item->login_successful ? __('auth_log.true') : __('auth_log.false'),
            ],
            'logout_at' => [
                'heading' => __('auth_log.logout_at'),
                'value' => fn($item) => self::safeDate($item, 'logout_at', 'Y-m-d H:i:s'),
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

    private static function safeDate($model, string $attribute, string $format = 'Y-m-d H:i:s'): ?string
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

        $queryBuilder->chunk($chunkSize, function ($authLogs) use ($writer, $columns, $onChunk) {
            if ($onChunk) {
                $onChunk();
            }

            foreach ($authLogs as $item) {
                $writer->addRow(Row::fromValues(self::mapRow($item, $columns)));
            }
        });

        $writer->close();
    }
}