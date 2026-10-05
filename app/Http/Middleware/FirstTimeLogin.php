<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FirstTimeLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->first_time_login) {
            return response()->json([
                'status' => false,
                'message' => 'You must change your password before continuing.',
                'action' => 'change_password',
                 'first_time_login' => true,
            ], 403); // Forbidden
        }

        return $next($request);
    }
}
