<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SessionMonitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets a signed-in user review and end their own sessions.
 */
class SessionController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected SessionMonitor $sessions) {}

    /**
     * List the sessions for the signed-in account.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/sessions', [
            'sessions' => $this->sessions->forUser($user, $request->session()->getId()),
            'supported' => $this->sessions->usesDatabaseDriver(),
        ]);
    }

    /**
     * End one of the signed-in user's other sessions.
     */
    public function destroy(Request $request, string $session): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($session === $request->session()->getId()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Use sign out to end the session you are using now.',
            ]);
        }

        $revoked = $this->sessions->revoke($user->id, $session);

        return back()->with('toast', [
            'type' => $revoked ? 'success' : 'error',
            'message' => $revoked ? 'Session ended.' : 'That session has already ended.',
        ]);
    }

    /**
     * End every other session for the signed-in account.
     */
    public function destroyOthers(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $revoked = $this->sessions->revokeForUser($user->id, $request->session()->getId());

        return back()->with('toast', [
            'type' => 'success',
            'message' => $revoked === 1
                ? 'Signed out of 1 other session.'
                : "Signed out of {$revoked} other sessions.",
        ]);
    }
}
