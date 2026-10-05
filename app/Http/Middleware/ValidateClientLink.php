<?php

namespace App\Http\Middleware;

use App\Models\Payment;
use App\Models\PaymentLink;
use App\Services\DebtorService;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ValidateClientLink
{
    public function handle(Request $request, Closure $next): Response
    {
        $clientLinkHeader = $request->header('x-client-link');

        if (! $clientLinkHeader) {
            throw (new AuthorizationException('x-client-link header is required'))->withStatus(401);
        }

        $authClient = Auth::guard('client-view')->user();

        if (! $authClient instanceof Payment && ! $authClient instanceof PaymentLink) {
            throw (new AuthorizationException('invalid client view session'))->withStatus(401);
        }

        if ($authClient->link !== $clientLinkHeader) {
            throw (new AuthorizationException('x-client-link does not match authenticated session'))->withStatus(401);
        }

        $routeLink = $request->route('link');
        

        if ($routeLink !== null && ! DebtorService::belongsToAuthenticatedClient($routeLink)) {
            throw (new AuthorizationException('requested data does not match authenticated session'))->withStatus(401);
        }

        return $next($request);
    }
}
