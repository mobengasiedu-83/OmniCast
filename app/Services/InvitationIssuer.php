<?php

namespace App\Services;

use App\Enums\Role as RoleEnum;
use App\Mail\UserInvitationMail;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Issues invitation records and mails the one-time acceptance link.
 *
 * Invitations are shared by the platform user directory and the client team
 * screen, so the token handling lives in one place.
 */
class InvitationIssuer
{
    /**
     * Create an invitation and queue its email.
     *
     * @param  array{email: string, name?: string|null, role: string, client_id?: int|null}  $attributes
     */
    public function issue(array $attributes, ?User $inviter = null): UserInvitation
    {
        $issued = UserInvitation::issue([
            'email' => $attributes['email'],
            'name' => $attributes['name'] ?? null,
            'role' => RoleEnum::from($attributes['role']),
            'client_id' => $attributes['client_id'] ?? null,
            'invited_by' => $inviter?->id,
        ]);

        $this->send($issued['invitation'], $issued['token']);

        return $issued['invitation'];
    }

    /**
     * Replace the invitation token and queue the email again.
     */
    public function resend(UserInvitation $invitation): void
    {
        $token = Str::random(64);

        $invitation->markResent(UserInvitation::hashToken($token));

        $this->send($invitation, $token);
    }

    /**
     * Queue the invitation email carrying the plain-text token.
     */
    protected function send(UserInvitation $invitation, string $token): void
    {
        Mail::to($invitation->email)->queue(new UserInvitationMail($invitation, $token));
    }
}
