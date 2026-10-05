<?php

namespace App\Http\Middleware;

use App\Enums\TwoFactorAuthentication;
use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerifiedLogin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Setting::twoFactorGeneration()->value == TwoFactorAuthentication::ENABLED->value && ! session()->has('otp_verified')) {
            Auth::user()->refresh();

            return redirect()->route('otp.login');
        }

        return $next($request);
    }
}
