<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConvertUserTokenCookieToHeader
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->bearerToken() && $request->cookie('auth_token') && !$request->header('x-client-link')) {
                $request->headers->set('Authorization', 'Bearer ' . $request->cookie('auth_token'));
        }

        return $next($request);
    }
}

