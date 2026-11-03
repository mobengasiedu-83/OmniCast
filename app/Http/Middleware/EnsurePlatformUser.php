<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects client users from the platform administration area.
 */
class EnsurePlatformUser
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null || ! $user->isPlatformUser(), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
