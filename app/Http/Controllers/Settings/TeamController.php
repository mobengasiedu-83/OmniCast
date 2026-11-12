<?php

namespace App\Http\Controllers\Settings;

use App\Concerns\RecordsActivity;
use App\Enums\ClientStatus;
use App\Enums\Role as RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\InviteUserRequest;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\InvitationIssuer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The client-facing team screen: colleagues and pending invitations.
 */
class TeamController extends Controller
{
    use RecordsActivity;

    /**
     * Create a new controller instance.
     */
    public function __construct(protected InvitationIssuer $invitations) {}

    /**
     * List the organisation's users and invitations.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $isClientActive = $user->client?->status === ClientStatus::Active;

        Gate::authorize('create', UserInvitation::class);

        abort_if($user->client_id === null, 403);

        $members = User::query()
            ->where('client_id', $user->client_id)
            ->with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'job_title' => $member->job_title,
                'roles' => $member->getRoleNames()
                    ->map(fn (string $name): string => RoleEnum::tryFrom($name)?->label() ?? Str::headline($name))
                    ->values()
                    ->all(),
                'is_active' => $member->isActive(),
                'is_self' => $member->id === $user->id,
                'two_factor_enabled' => $member->two_factor_confirmed_at !== null,
                'last_login_at' => $member->last_login_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $invitations = UserInvitation::query()
            ->where('client_id', $user->client_id)
            ->with('inviter:id,name')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (UserInvitation $invitation): array => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'name' => $invitation->name,
                'role_label' => $invitation->roleLabel(),
                'inviter' => $invitation->inviter?->name,
                'status' => $invitation->effectiveStatus()->value,
                'status_label' => $invitation->effectiveStatus()->label(),
                'is_pending' => $invitation->isAcceptable(),
                'expires_at' => $invitation->expires_at?->toIso8601String(),
                'created_at' => $invitation->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('settings/team', [
            'members' => $members,
            'invitations' => $invitations,
            'roles' => array_map(
                fn (RoleEnum $role): array => ['value' => $role->value, 'label' => $role->label()],
                RoleEnum::clientRoles(),
            ),
            'stats' => [
                'members' => count($members),
                'pending' => UserInvitation::query()
                    ->where('client_id', $user->client_id)
                    ->outstanding()
                    ->count(),
                'mfa' => User::query()
                    ->where('client_id', $user->client_id)
                    ->whereNotNull('two_factor_confirmed_at')
                    ->count(),
            ],
            'can' => [
                'invite' => Gate::allows('create', UserInvitation::class) && $isClientActive,
            ],
            'clientActive' => $isClientActive,
        ]);
    }

    /**
     * Invite a colleague into the organisation.
     */
    public function store(InviteUserRequest $request): RedirectResponse
    {
        if ($request->user()->client?->status !== ClientStatus::Active) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Your organisation can invite team members after account approval.',
            ]);
        }

        $invitation = $this->invitations->issue($request->invitationAttributes(), $request->user());

        $this->recordActivity('invited teammate', $invitation, [
            'email' => $invitation->email,
            'role' => $invitation->role,
            'client_id' => $invitation->client_id,
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Invitation sent to {$invitation->email}.",
        ]);
    }

    /**
     * Send a fresh invitation link to a colleague.
     */
    public function resend(Request $request, UserInvitation $invitation): RedirectResponse
    {
        Gate::authorize('resend', $invitation);

        if (! $invitation->isAcceptable()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Only pending invitations can be resent.',
            ]);
        }

        $this->invitations->resend($invitation);

        $this->recordActivity('resent teammate invitation', $invitation, ['email' => $invitation->email]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Invitation resent to {$invitation->email}.",
        ]);
    }

    /**
     * Withdraw a colleague's invitation.
     */
    public function destroy(Request $request, UserInvitation $invitation): RedirectResponse
    {
        Gate::authorize('revoke', $invitation);

        if (! $invitation->isPending()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Only pending invitations can be revoked.',
            ]);
        }

        $invitation->revoke();

        $this->recordActivity('revoked teammate invitation', $invitation, ['email' => $invitation->email]);

        return back()->with('toast', [
            'type' => 'warning',
            'message' => "Invitation to {$invitation->email} revoked.",
        ]);
    }
}
