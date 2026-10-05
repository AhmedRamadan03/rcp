<?php

namespace App\Http\Middleware;

use App\Exceptions\NotAuthorizeException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanctumAbilitiesCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$abilities): Response
    {
        foreach ($abilities as $ability) {
            if (! $request->user()->tokenCan($ability)) {
                throw new NotAuthorizeException('User Does not have abilities to access this ');
            }
        }

        return $next($request);
    }
}
