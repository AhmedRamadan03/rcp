<?php

namespace App\Http\Middleware;

use App\Enums\Roles;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DashboardMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! is_null(auth()->user()) && in_array(auth()->user()->role_id, [Roles::COLLECTOR->value, Roles::EXTERNAL_OFFICE->value, Roles::LEGAL_DEPARTMENT->value])) {
            // return redirect()->route('claims.index');
        }

        return $next($request);
    }
}
