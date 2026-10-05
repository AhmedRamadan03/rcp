<?php

namespace App\Support;

use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanInterface;

/**
 * Small seam over the OpenTelemetry API for the few places that add context to
 * the spans opened by the auto-instrumentation.
 */
final class Tracing
{
    /**
     * The span for the current request, job or command, or null when tracing is
     * off (no OTEL_PHP_AUTOLOAD_ENABLED, excluded URL, or a non-instrumented process).
     */
    public static function currentSpan(): ?SpanInterface
    {
        $span = Span::getCurrent();

        return $span->getContext()->isValid() ? $span : null;
    }

    public static function currentTraceId(): ?string
    {
        return self::currentSpan()?->getContext()->getTraceId();
    }
}
