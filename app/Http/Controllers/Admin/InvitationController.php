<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\RecordsActivity;
use App\Enums\InvitationStatus;
use App\Enums\Role as RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\InviteUserRequest;
use App\Models\Client;
use App\Models\UserInvitation;
use App\Services\InvitationIssuer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Issues, resends and withdraws invitations.
 */
class InvitationController extends Controller
{
    use RecordsActivity;

    /**
     * Create a new controller instance.
     */
    public function __construct(protected InvitationIssuer $invitations) {}

    /**
     * List every invitation sent from the platform.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', UserInvitation::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'status' => $request->string('status')->toString(),
        ];

        /** @var LengthAwarePaginator<int, array<string, mixed>> $invitations */
        $invitations = UserInvitation::query()
            ->with(['client:id,name', 'inviter:id,name'])
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $term = '%'.$filters['search'].'%';

                $query->where(function ($query) use ($term): void {
                    $query->where('email', 'like', $term)->orWhere('name', 'like', $term);
                });
            })
            ->when($filters['status'] !== '', function ($query) use ($filters): void {
                if ($filters['status'] === InvitationStatus::Expired->value) {
                    $query->where('status', InvitationStatus::Pending->value)
                        ->where('expires_at', '<=', now());

                    return;
                }

                $query->where('status', $filters['status']);
            })
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (UserInvitation $invitation): array => $this->row($invitation));

        return Inertia::render('admin/users/invitations', [
            'invitations' => $invitations,
            'filters' => $filters,
            'stats' => [
                'pending' => UserInvitation::query()->outstanding()->count(),
                'accepted' => UserInvitation::query()->where('status', InvitationStatus::Accepted->value)->count(),
                'expired' => UserInvitation::query()
                    ->where('status', InvitationStatus::Pending->value)
                    ->where('expires_at', '<=', now())
                    ->count(),
                'revoked' => UserInvitation::query()->where('status', InvitationStatus::Revoked->value)->count(),
            ],
            'statuses' => array_map(
                fn (InvitationStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                InvitationStatus::cases(),
            ),
            'roles' => array_map(
                fn (RoleEnum $role): array => ['value' => $role->value, 'label' => $role->label()],
                RoleEnum::cases(),
            ),
            'clients' => Client::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Client $client): array => ['id' => $client->id, 'name' => $client->name])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Invite somebody to the platform team or to a client tenant.
     */
    public function store(InviteUserRequest $request): RedirectResponse
    {
        $invitation = $this->invitations->issue($request->invitationAttributes(), $request->user());

        $this->recordActivity('invited user', $invitation, [
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
     * Send a fresh invitation link for a pending invitation.
     */
    public function resend(Request $request, UserInvitation $invitation): RedirectResponse
    {
        Gate::authorize('resend', $invitation);

        if (! $invitation->isPending()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Only pending invitations can be resent.',
            ]);
        }

        $this->invitations->resend($invitation);

        $this->recordActivity('resent invitation', $invitation, ['email' => $invitation->email]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Invitation resent to {$invitation->email}.",
        ]);
    }

    /**
     * Withdraw a pending invitation.
     */
    public function revoke(Request $request, UserInvitation $invitation): RedirectResponse
    {
        Gate::authorize('revoke', $invitation);

        if (! $invitation->isPending()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Only pending invitations can be revoked.',
            ]);
        }

        $invitation->revoke();

        $this->recordActivity('revoked invitation', $invitation, ['email' => $invitation->email]);

        return back()->with('toast', [
            'type' => 'warning',
            'message' => "Invitation to {$invitation->email} revoked.",
        ]);
    }

    /**
     * Remove an invitation that can no longer be accepted.
     */
    public function destroy(Request $request, UserInvitation $invitation): RedirectResponse
    {
        Gate::authorize('revoke', $invitation);

        $email = $invitation->email;

        $invitation->delete();

        $this->recordActivity('deleted invitation', null, ['email' => $email]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Invitation to {$email} deleted.",
        ]);
    }

    /**
     * Present an invitation for the table.
     *
     * @return array<string, mixed>
     */
    protected function row(UserInvitation $invitation): array
    {
        $status = $invitation->effectiveStatus();

        return [
            'id' => $invitation->id,
            'email' => $invitation->email,
            'name' => $invitation->name,
            'role' => $invitation->role,
            'role_label' => $invitation->roleLabel(),
            'client' => $invitation->client?->name,
            'inviter' => $invitation->inviter?->name,
            'status' => $status->value,
            'status_label' => $status->label(),
            'is_pending' => $invitation->isAcceptable(),
            'expires_at' => $invitation->expires_at?->toIso8601String(),
            'accepted_at' => $invitation->accepted_at?->toIso8601String(),
            'last_sent_at' => $invitation->last_sent_at?->toIso8601String(),
            'created_at' => $invitation->created_at?->toIso8601String(),
        ];
    }
}
