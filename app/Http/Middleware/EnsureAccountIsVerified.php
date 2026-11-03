<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsVerified
{
    public function handle(Request $request, Closure $next, ?string $redirectToRoute = null): Response
    {
        $user = $request->user();
        if ($user !== null && ($user->hasVerifiedEmail() || $user->phone_verified_at !== null)) {
            return $next($request);
        }

        return $request->expectsJson()
            ? abort(403, 'Your email address or phone number is not verified.')
            : Redirect::guest(URL::route($redirectToRoute ?? 'verification.notice'));
    }
}
