<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires the bearer token to hold one of the named abilities.
 *
 * Sanctum 4 no longer ships an ability middleware, so the check lives here: a
 * `read` token cannot reach a screen that needs `write`, and a platform token
 * granted `*` satisfies both.
 */
class EnsureApiAbility
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(Response::HTTP_UNAUTHORIZED, 'An access token is required.');
        }

        foreach ($abilities as $ability) {
            if ($user->tokenCan($ability)) {
                return $next($request);
            }
        }

        abort(Response::HTTP_FORBIDDEN, 'This token does not hold the required ability.');
    }
}
