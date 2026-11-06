<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Checks the Cloudflare Turnstile token a form was submitted with.
 *
 * The widget solves the challenge in the browser and hands back a single use
 * token. Only Cloudflare can say whether that token is genuine, so it is posted
 * to the `siteverify` endpoint from the server with the secret key. When the
 * check is not configured every token is accepted, which keeps local work and
 * the test suite on the plain form.
 */
class TurnstileVerifier
{
    /**
     * Whether the challenge is armed for this environment.
     */
    public function enabled(): bool
    {
        return filter_var(config('services.turnstile.enabled'), FILTER_VALIDATE_BOOL)
            && $this->siteKey() !== null
            && $this->secretKey() !== null;
    }

    /**
     * The public key the browser renders the widget with.
     */
    public function siteKey(): ?string
    {
        return $this->configured('site_key');
    }

    /**
     * Whether the token was issued for the form the client just submitted.
     */
    public function verify(?string $token, ?string $ip = null): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        $token = trim((string) $token);

        if ($token === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('services.turnstile.timeout', 10))
                ->post((string) config('services.turnstile.verify_url'), array_filter([
                    'secret' => $this->secretKey(),
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (ConnectionException $exception) {
            Log::warning('Turnstile could not be reached.', ['message' => $exception->getMessage()]);

            return false;
        }

        if ($response->successful() && $response->json('success') === true) {
            return true;
        }

        Log::warning('Turnstile rejected a token.', [
            'status' => $response->status(),
            'errors' => $response->json('error-codes'),
        ]);

        return false;
    }

    /**
     * The secret key the token is verified with.
     */
    protected function secretKey(): ?string
    {
        return $this->configured('secret_key');
    }

    /**
     * A Turnstile setting, or null when it was left blank in the environment.
     */
    protected function configured(string $key): ?string
    {
        $value = trim((string) config('services.turnstile.'.$key));

        return $value === '' ? null : $value;
    }
}
