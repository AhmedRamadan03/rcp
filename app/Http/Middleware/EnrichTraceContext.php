<?php

namespace App\Http\Middleware;

use App\Support\Tracing;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Adds "who" and "where" to the request span that the OpenTelemetry Laravel
 * auto-instrumentation opens for every request, and returns the trace id to
 * the browser so a customer-facing error can be found in SigNoz.
 *
 * Everything here is best effort and must be free: no queries, and an
 * observability failure must never affect the response.
 */
class EnrichTraceContext
{
    public const TRACE_HEADER = 'X-Trace-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $this->enrich($request);
        } catch (Throwable) {
            // never let telemetry break a request
        }

        try {
            $this->exposeTraceId($response);
        } catch (Throwable) {
            // guarded separately: a failure above must not cost the user the reference id
        }

        return $response;
    }

    private function enrich(Request $request): void
    {
        $span = Tracing::currentSpan();
        if ($span === null) {
            return;
        }

        $attributes = [
            'app.tenant' => config('app.client_name'),
            'app.env' => config('app.env'),
            'app.locale' => $request->header('Accept-Language'),
        ];

        // Staff / partner users. Only read a guard that has already resolved a user
        // (the auth middleware did that on protected routes). Calling user() blindly on a
        // public route would make the session or token guard run a lookup just for us.
        if (Auth::hasUser() && ($user = Auth::user())) {
            $attributes['enduser.id'] = (string) $user->getAuthIdentifier();
            $attributes['enduser.role_id'] = $user->role_id ?? null;
            if ($user->relationLoaded('role')) {
                $attributes['enduser.role'] = $user->role?->name;
            }
        }

        // Debtors using the client view. Same rule: the guard must already hold the user.
        // The session model is a Payment (has claim_id) or a PaymentLink (claim only
        // reachable through its payment relation, read only if already loaded).
        $clientGuard = Auth::guard('client-view');
        if ($request->hasHeader('x-client-link') && $clientGuard->hasUser() && ($client = $clientGuard->user())) {
            $attributes['app.client.type'] = class_basename($client);
            $attributes['app.client.id'] = (string) $client->getKey();
            $attributes['app.claim.id'] = $client->getAttribute('claim_id')
                ?? ($client->relationLoaded('payment') ? $client->payment?->claim_id : null);
        }

        $span->setAttributes(array_filter($attributes, static fn ($v) => $v !== null && $v !== ''));
    }

    private function exposeTraceId(Response $response): void
    {
        if ($traceId = Tracing::currentTraceId()) {
            $response->headers->set(self::TRACE_HEADER, $traceId);
        }
    }
}
