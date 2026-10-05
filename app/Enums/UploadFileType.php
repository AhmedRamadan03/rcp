<?php

namespace App\Enums;

enum UploadFileType: string
{
    case XLSX = 'xlsx';
    case XLS  = 'xls';
    case JPEG = 'jpeg';
    case JPG  = 'jpg';
    case PNG  = 'png';
    case SVG  = 'svg';
    case PDF  = 'pdf';

    /**
     * كل الـ extensions - يتستخدم مع Rule::mimes()
     */
    public static function extensions(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }

    /**
     * كل الـ extensions ما عدا SVG - للاستخدام العادي الآمن
     */
    public static function extensionsWithoutSvg(): array
    {
        return array_map(
            fn ($case) => $case->value,
            array_filter(self::cases(), fn ($case) => $case !== self::SVG)
        );
    }


    /**
     * الـ MIME type الحقيقي المقابل لكل extension
     */
    public function mimeType(): string
    {
        return match ($this) {
            self::XLSX => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::XLS  => 'application/vnd.ms-excel',
            self::JPEG, self::JPG => 'image/jpeg',
            self::PNG  => 'image/png',
            self::SVG  => 'image/svg+xml',
            self::PDF  => 'application/pdf',
        };
    }

    public static function mimeTypes(): array
    {
        return array_unique(array_map(fn ($case) => $case->mimeType(), self::cases()));
    }
}