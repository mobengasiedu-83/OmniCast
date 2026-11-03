<?php

use App\Enums\InvitationStatus;
use App\Enums\Role as RoleEnum;
use App\Mail\UserInvitationMail;
use App\Models\Client;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Issue an invitation directly so the test knows the plain-text token.
 *
 * @param  array{email?: string, name?: string|null, role?: RoleEnum, client_id?: int|null}  $overrides
 * @return array{invitation: UserInvitation, token: string}
 */
function identityInvite(User $inviter, array $overrides = []): array
{
    return UserInvitation::issue([
        'email' => $overrides['email'] ?? 'invitee@example.com',
        'name' => $overrides['name'] ?? 'Invitee',
        'role' => $overrides['role'] ?? RoleEnum::ClientOperator,
        'client_id' => $overrides['client_id'] ?? null,
        'invited_by' => $inviter->id,
    ]);
}

it('queues an invitation email for a new staff member', function () {
    Mail::fake();

    $admin = platformUser();

    $this->actingAs($admin)
        ->post(route('admin.invitations.store'), [
            'email' => 'New.Staff@Example.com',
            'name' => 'New Staff',
            'role' => RoleEnum::FinanceOfficer->value,
            'client_id' => '',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $invitation = UserInvitation::query()
        ->where('email', 'new.staff@example.com')
        ->first();

    expect($invitation)->not->toBeNull()
        ->and($invitation?->status)->toBe(InvitationStatus::Pending)
        ->and($invitation?->token)->toHaveLength(64)
        ->and($invitation?->invited_by)->toBe($admin->id);

    Mail::assertQueued(UserInvitationMail::class);
});

it('accepts an invitation and signs the new user in', function () {
    $admin = platformUser();
    $client = Client::factory()->create();

    ['invitation' => $invitation, 'token' => $token] = identityInvite($admin, [
        'client_id' => $client->id,
    ]);

    $this->get(route('invitations.accept.show', $token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/accept-invitation')
            ->where('isValid', true)
            ->where('email', 'invitee@example.com'));

    $this->post(route('invitations.accept.store', $token), [
        'name' => 'Ama Boateng',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();

    $user = $invitation->fresh()?->acceptedBy;

    expect($user?->name)->toBe('Ama Boateng')
        ->and($user?->client_id)->toBe($client->id)
        ->and($user?->hasRole(RoleEnum::ClientOperator->value))->toBeTrue()
        ->and($user?->email_verified_at)->not->toBeNull()
        ->and($invitation->fresh()?->status)->toBe(InvitationStatus::Accepted);
});

it('refuses an expired invitation', function () {
    $admin = platformUser();

    ['invitation' => $invitation, 'token' => $token] = identityInvite($admin);

    $invitation->forceFill(['expires_at' => now()->subDay()])->save();

    $this->get(route('invitations.accept.show', $token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('isValid', false));

    $this->post(route('invitations.accept.store', $token), [
        'name' => 'Too Late',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('invitations.accept.show', $token));

    expect(User::query()->where('email', 'invitee@example.com')->exists())->toBeFalse();
});

it('refuses a withdrawn invitation', function () {
    $admin = platformUser();

    ['invitation' => $invitation, 'token' => $token] = identityInvite($admin);

    $invitation->revoke();

    $this->post(route('invitations.accept.store', $token), [
        'name' => 'Blocked',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect();

    expect(User::query()->where('email', 'invitee@example.com')->exists())->toBeFalse();
});

it('rejects an invitation for an address that already has an account', function () {
    $admin = platformUser();
    $existing = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.invitations.store'), [
            'email' => $existing->email,
            'role' => RoleEnum::FinanceOfficer->value,
            'client_id' => '',
        ])
        ->assertSessionHasErrors('email');
});

it('rejects a second pending invitation for the same address', function () {
    $admin = platformUser();

    identityInvite($admin);

    $this->actingAs($admin)
        ->post(route('admin.invitations.store'), [
            'email' => 'invitee@example.com',
            'role' => RoleEnum::FinanceOfficer->value,
            'client_id' => '',
        ])
        ->assertSessionHasErrors('email');
});

it('forbids client users from the platform invitation screen', function () {
    $client = Client::factory()->create();
    $clientAdmin = User::factory()->forClient($client)->create();
    $clientAdmin->syncRoles([RoleEnum::ClientAdmin->value]);

    $this->actingAs($clientAdmin)
        ->get(route('admin.invitations.index'))
        ->assertForbidden();
});
