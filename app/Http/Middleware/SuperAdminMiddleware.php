<?php

namespace App\Http\Middleware;

use App\Enums\Roles;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect('login');
        }
        //        if ($request->user()->role_id != Roles::SUPER_ADMIN->value) {
        //            abort(Response::HTTP_FORBIDDEN);
        //        }

        return $next($request);
    }
}
