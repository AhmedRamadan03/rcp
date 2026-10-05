<?php

namespace App\Services;

class FilesService
{
    /**
     * Normalize a GCS object path:
     *  - Collapses duplicate slashes (including backslashes)
     *  - Strips leading and trailing slashes
     */
    public static function normalizePath(string $path): string
    {
        $path = preg_replace('#[/\\\\]+#', '/', $path);

        return trim($path, '/');
    }

    /**
     * Sanitize a filename so it is safe to use as a GCS object name segment.
     */
    public static function sanitizeFileName(string $fileName): string
    {
        $fileName = strtolower($fileName);
        $fileName = preg_replace('/\s+/', '-', $fileName);
        $fileName = preg_replace('/[^a-z0-9.\-_]/', '', $fileName);
        $fileName = preg_replace('/-{2,}/', '-', $fileName);

        return trim($fileName, '-');
    }

    /**
     * Build the full object path from a directory and a filename.
     */
    public static function buildObjectPath(string $directory, string $fileName): string
    {
        $directory = self::normalizePath($directory);
        $fileName  = self::sanitizeFileName(self::normalizePath($fileName));

        return $directory . '/' . $fileName;
    }
}
