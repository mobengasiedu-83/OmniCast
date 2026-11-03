<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out accounts that were suspended while they were signed in.
 */
class EnsureAccountIsActive
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $user->isActive()) {
            return $this->signOut($request, __('This account has been suspended. Contact your platform administrator.'));
        }

        $client = $user->client;

        if ($client !== null && $client->status->blocksSignIn()) {
            return $this->signOut($request, __(
                'Your organisation :client is :status. Contact your platform administrator.',
                ['client' => $client->name, 'status' => mb_strtolower($client->status->label())],
            ));
        }

        return $next($request);
    }

    /**
     * End the session and return the user to the sign-in screen with a reason.
     */
    protected function signOut(Request $request, string $message): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
