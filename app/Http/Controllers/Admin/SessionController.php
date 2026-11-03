<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\RecordsActivity;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SessionMonitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The platform-wide view of who is signed in right now.
 */
class SessionController extends Controller
{
    use RecordsActivity;

    /**
     * Create a new controller instance.
     */
    public function __construct(protected SessionMonitor $sessions) {}

    /**
     * List every live authenticated session.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
        ];

        return Inertia::render('admin/users/sessions', [
            'sessions' => $this->sessions->paginate($filters)->withQueryString(),
            'filters' => $filters,
            'stats' => [
                'sessions' => DB::table('sessions')->whereNotNull('user_id')->count(),
                'users' => DB::table('sessions')->whereNotNull('user_id')->distinct()->count('user_id'),
                'guests' => DB::table('sessions')->whereNull('user_id')->count(),
            ],
            'supported' => $this->sessions->usesDatabaseDriver(),
        ]);
    }

    /**
     * Sign a user out of every device.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('signOut', $user);

        $revoked = $this->sessions->revokeForUser($user->id);

        $this->recordActivity('ended user sessions', $user, ['sessions' => $revoked]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $revoked === 1
                ? "{$user->name} signed out of 1 session."
                : "{$user->name} signed out of {$revoked} sessions.",
        ]);
    }

    /**
     * Sign a user out of a single device.
     */
    public function destroyOne(Request $request, User $user, string $session): RedirectResponse
    {
        Gate::authorize('signOut', $user);

        $revoked = $this->sessions->revoke($user->id, $session);

        if ($revoked) {
            $this->recordActivity('ended user session', $user, ['session_id' => $session]);
        }

        return back()->with('toast', [
            'type' => $revoked ? 'success' : 'error',
            'message' => $revoked ? 'Session ended.' : 'That session has already ended.',
        ]);
    }
}
