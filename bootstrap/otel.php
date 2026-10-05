<?php

/*
|--------------------------------------------------------------------------
| OpenTelemetry environment shim
|--------------------------------------------------------------------------
|
| The OpenTelemetry SDK decides whether to enable itself while Composer's
| autoloader is being loaded, by reading OTEL_* variables from the process
| environment. That happens before Laravel parses the .env file, so OTEL_*
| values placed in .env alone never reach the SDK and tracing silently stays
| off. This shim runs before vendor/autoload.php and exports the OTEL_* lines
| of .env into the process environment.
|
| Variables that are already present in the process environment (php-fpm
| env[], supervisor environment=, cron, a php.ini file) always win, so an
| operator can still override per process. Everything else in .env is left
| for Laravel to load as usual.
|
*/

require __DIR__.'/otel-env.php';

otel_export_env_from_file(($_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)).'/.env');
