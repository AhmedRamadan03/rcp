<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConvertClientTokenCookieToHeader
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->bearerToken() && $request->cookie('client_access_token')) {
            $request->headers->set('Authorization', 'Bearer ' . $request->cookie('client_access_token'));
        }
        return $next($request);
    }
}