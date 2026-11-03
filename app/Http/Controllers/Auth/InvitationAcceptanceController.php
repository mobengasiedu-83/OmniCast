<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Lets an invitee set a password and activate their account.
 */
class InvitationAcceptanceController extends Controller
{
    /**
     * Show the invitation acceptance form.
     */
    public function show(string $token): Response
    {
        $invitation = UserInvitation::findForToken($token);

        abort_if($invitation === null, HttpResponse::HTTP_NOT_FOUND);

        return Inertia::render('auth/accept-invitation', [
            'token' => $token,
            'email' => $invitation->email,
            'name' => $invitation->name,
            'emailInUse' => User::query()->where('email', $invitation->email)->exists(),
            'roleLabel' => $invitation->roleLabel(),
            'organisation' => $invitation->client?->name,
            'inviter' => $invitation->inviter?->name,
            'expiresAt' => $invitation->expires_at?->toFormattedDayDateString(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'isValid' => $invitation->isAcceptable(),
        ]);
    }

    /**
     * Activate the invited account and sign the user in.
     */
    public function store(Request $request, string $token): RedirectResponse
    {
        $invitation = UserInvitation::findForToken($token);

        abort_if($invitation === null, HttpResponse::HTTP_NOT_FOUND);

        if (! $invitation->isAcceptable()) {
            return redirect()
                ->route('invitations.accept.show', ['token' => $token])
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'This invitation is no longer valid. Ask an administrator for a new one.',
                ]);
        }

        if (User::query()->where('email', $invitation->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => __('An account already exists for this email address. Sign in instead.'),
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($invitation, $validated): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $invitation->email,
                'password' => $validated['password'],
                'client_id' => $invitation->client_id,
                'is_active' => true,
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();
            $user->syncRoles([$invitation->role]);

            $invitation->accept($user);

            return $user;
        });

        Auth::guard('web')->login($user);

        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('toast', [
            'type' => 'success',
            'message' => 'Your account is ready. Welcome to '.config('app.name').'.',
        ]);
    }
}
