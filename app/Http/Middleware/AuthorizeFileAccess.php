<?php

namespace App\Http\Middleware;

use App\Enums\FileSource;
use App\Models\Claim;
use App\Models\File;
use App\Models\PaymentLink;
use App\Models\Payment;
use App\Models\User;
use App\Services\DebtorService;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;

class AuthorizeFileAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->route('key');
        $file = ($key && Str::isUuid($key)) ? File::where('uuid', $key)->first() : null;

        // public file like profile image
        if ($file && $file->source && FileSource::tryFrom($file->source)?->publicSource()) {
            return $next($request);
        }

        // all file guarded 
        // if ($request->routeIs('download') && $file) {
        //     if (in_array($file->source, [FileSource::VOUCHERS->value, FileSource::DISCHARGE_FILES->value])) {
        //         return redirect()->route(
        //             'public.file',
        //             array_merge(['key' => $file->uuid], $request->query())
        //         );
        //     }
        // }

        $clientAuth = Auth::guard('client-view')->user();

        if ($clientAuth instanceof PaymentLink || $clientAuth instanceof Payment) {
            $claimId = $file?->resolveClaimId();
            if ($claimId !== null) {  
                $authClaim = DebtorService::getAuthenticatedClaim();
                if ($authClaim === null ||(int) $authClaim->id !== (int) $claimId) {  
                    throw new AuthorizationException();  
                }  
            }  
            return $next($request);
        }

        /** @var User|null $user */
        $user = Auth::guard('sanctum')->user();

        if ($user instanceof User) {
            $claimId = $file?->resolveClaimId();

            
            if ($claimId === null) {
                return $next($request);
            }

            $isVisible = Claim::query()
                ->visibleTo($user)
                ->whereKey($claimId)
                ->exists();

            if (!$isVisible) {
                throw new AuthorizationException();
            }

            return $next($request);
        }
        
        //  Unauthenticated (came from SMS link) → redirect to client-view URL.  
        return $this->redirectToClientView($file);  
    }

    private function redirectToClientView(?File $file)
    {
        $claimFromFile = $file?->resolveClaim();
        $baseLink = $claimFromFile?->payment?->link ?? $claimFromFile?->firstLinkWithoutStatus?->link;

        if ($baseLink) {
            return redirect()->away(config('app.frontend_url') . '/claim-info/' . $baseLink);
        }
        throw new AuthorizationException();
    }

}