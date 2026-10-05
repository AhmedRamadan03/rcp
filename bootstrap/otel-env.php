<?php

/*
 * Exports OTEL_* lines from a dotenv file into the process environment.
 *
 * This is a deliberately tiny dotenv parser: it runs before vendor/autoload.php,
 * so nothing from vendor (including vlucas/phpdotenv) is loadable yet. It supports
 * only what the OTEL_* variables need:
 *   - KEY=value, optional leading "export ", surrounding single or double quotes
 *   - a trailing " # comment" on unquoted values
 * It does not unescape sequences inside quoted values, expand ${VARS}, or handle
 * multi-line values. Everything else in .env is left for Laravel to parse as usual.
 *
 * Kept free of side effects so it can be unit tested; bootstrap/otel.php calls it.
 */

if (! function_exists('otel_export_env_from_file')) {
    /**
     * Export OTEL_* lines from a dotenv file into the process environment.
     *
     * @return array<string, string> the variables that were exported
     */
    function otel_export_env_from_file(string $file): array
    {
        if (! is_readable($file)) {
            return [];
        }

        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $exported = [];

        foreach ($lines as $line) {
            $line = ltrim($line);
            if (str_starts_with($line, 'export ')) {
                $line = ltrim(substr($line, 7));
            }
            if (! str_starts_with($line, 'OTEL_') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = rtrim($key);
            if (! preg_match('/^OTEL_[A-Z0-9_]+$/', $key)) {
                continue;
            }

            if (getenv($key) !== false || isset($_SERVER[$key]) || isset($_ENV[$key])) {
                continue; // process environment always wins
            }

            $value = trim($value);
            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $quote = $value[0];
                $end = strpos($value, $quote, 1);
                $value = $end === false ? substr($value, 1) : substr($value, 1, $end - 1);
            } else {
                $value = trim((string) preg_replace('/\s+#.*$/', '', $value));
            }

            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            $exported[$key] = $value;
        }

        return $exported;
    }
}
