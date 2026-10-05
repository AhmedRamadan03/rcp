<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Tracing;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Receives errors caught in the browser (React error boundary, unhandled
 * promise rejections, network failures) and records them on the current
 * request span so they appear in SigNoz next to backend exceptions.
 *
 * Public and rate limited: errors also happen on the login page and in the
 * unauthenticated client view.
 */
class ClientErrorController extends Controller
{
    public function store(Request $request): Response
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'stack' => ['nullable', 'string', 'max:20000'],
            'source' => ['nullable', 'string', 'max:100'],
            'url' => ['nullable', 'string', 'max:2000'],
            'app_version' => ['nullable', 'string', 'max:100'],
            'trace_id' => ['nullable', 'regex:/^[0-9a-f]{32}$/'],
            'extra' => ['nullable', 'array', 'max:20', function (string $attribute, mixed $value, \Closure $fail) {
                foreach (array_keys((array) $value) as $key) {
                    if (mb_strlen((string) $key) > 100) {
                        $fail("The {$attribute} keys must not be greater than 100 characters.");
                    }
                }
            }],
            'extra.*' => ['nullable', function (string $attribute, mixed $value, \Closure $fail) {
                if (! is_scalar($value)) {
                    $fail("The {$attribute} field must be a scalar value.");
                } elseif (is_string($value) && mb_strlen($value) > 2000) {
                    $fail("The {$attribute} field must not be greater than 2000 characters.");
                }
            }],
        ]);

        $report = [
            'message' => self::singleLine($data['message']),
            'stack' => $data['stack'] ?? '',
            'source' => self::singleLine($data['source'] ?? 'unknown'),
            'url' => self::singleLine($data['url'] ?? ''),
            'app_version' => self::singleLine($data['app_version'] ?? ''),
            'server_trace_id' => $data['trace_id'] ?? '',
            'user_agent' => self::singleLine((string) $request->userAgent()),
            'ip' => (string) $request->ip(),
            'extra' => self::singleLineExtra($data['extra'] ?? []),
        ];

        // Backup record in laravel.log. Note: with OTEL_LOGS_EXPORTER=none this line does
        // not reach SigNoz; the span event below is the only SigNoz record.
        // The stack keeps its line breaks for the span event only: Monolog's LineFormatter
        // turns the JSON-escaped \n of a context value back into a real line break.
        Log::warning('frontend: '.$report['message'], ['stack' => self::singleLine($report['stack'])] + $report);

        $this->recordOnSpan($report);

        return response()->noContent();
    }

    /**
     * Record the browser error as an OpenTelemetry "exception" span event, which is
     * what SigNoz lists on its Exceptions page.
     *
     * Deliberately no setStatus(ERROR): that would count every browser report as a
     * failed backend request and inflate the service error rate. Dashboards and
     * alerts key on the frontend.error attribute instead.
     */
    private function recordOnSpan(array $report): void
    {
        try {
            $span = Tracing::currentSpan();
            if ($span === null) {
                return;
            }

            $span->addEvent('exception', [
                'exception.type' => 'FrontendError.'.$report['source'],
                'exception.message' => $report['message'],
                'exception.stacktrace' => $report['stack'],
                'frontend.url' => $report['url'],
                'frontend.app_version' => $report['app_version'],
                'frontend.server_trace_id' => $report['server_trace_id'],
            ]);
            $span->setAttribute('frontend.error', true);
        } catch (Throwable) {
            // telemetry must never fail the request
        }
    }

    /**
     * Unauthenticated free text goes into laravel.log; strip control characters so a
     * caller cannot forge log lines.
     */
    private static function singleLine(string $value): string
    {
        return trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value));
    }

    /**
     * Same treatment for the free-form "extra" map: both keys and string values are
     * caller controlled. Values are already validated as scalar or null.
     */
    private static function singleLineExtra(array $extra): array
    {
        $clean = [];
        foreach ($extra as $key => $value) {
            $clean[self::singleLine((string) $key)] = is_string($value) ? self::singleLine($value) : $value;
        }

        return $clean;
    }
}
