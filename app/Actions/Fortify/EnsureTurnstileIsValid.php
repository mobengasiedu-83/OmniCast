<?php

namespace App\Actions\Fortify;

use App\Services\TurnstileVerifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Refuses a sign-in that did not carry a valid Cloudflare Turnstile token.
 *
 * The step runs in Fortify's login pipeline ahead of the credential check, so a
 * scripted attempt never reaches the password comparison and never counts
 * against the account's rate limit. When Turnstile is not configured the step
 * waves the request straight through.
 */
class EnsureTurnstileIsValid
{
    public function __construct(protected TurnstileVerifier $turnstile) {}

    /**
     * Handle the incoming login request.
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
