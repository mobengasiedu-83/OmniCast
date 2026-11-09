<?php

namespace App\Http\Middleware;

use App\Services\TurnstileVerifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Refuses a form submission that did not carry a valid Turnstile token.
 *
 * The route decides whether the challenge applies; this middleware only runs the
 * check, and waves the request straight through when the platform has no keys
 * configured, so local work and the test suite keep the plain form.
 */
class VerifyTurnstile
{
    public function __construct(protected TurnstileVerifier $turnstile) {}

    /**
     * Handle the incoming request.
     *
     * @param  Closure(Request): mixed  $next
     *
     * @throws ValidationException
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (! $this->turnstile->enabled()) {
            return $next($request);
        }

        if (! $this->turnstile->verify($request->input('cf-turnstile-response'), $request->ip())) {
            throw ValidationException::withMessages([
                'cf-turnstile-response' => 'We could not confirm that you are human. Complete the security check and try again.',
            ]);
        }

        return $next($request);
    }
}
