<?php

use App\Enums\ClientStatus;
use App\Enums\Role as RoleEnum;
use App\Mail\UserInvitationMail;
use App\Models\Client;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

it('invites a colleague into the organisation of a client admin', function () {
    Mail::fake();

    [$client, , $clientAdmin] = clientWithWallet();
    $otherClient = Client::factory()->create();

    $this->actingAs($clientAdmin)
        ->post(route('team.store'), [
            'email' => 'teammate@example.com',
            'role' => RoleEnum::ClientOperator->value,
            'client_id' => (string) $otherClient->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $invitation = UserInvitation::query()
        ->where('email', 'teammate@example.com')
        ->first();

    expect($invitation)->not->toBeNull()
        ->and($invitation?->client_id)->toBe($client->id);

    Mail::assertQueued(UserInvitationMail::class);
});

it('forbids client operators from inviting colleagues', function () {
    $client = Client::factory()->create();
    $operator = User::factory()->forClient($client)->create();
    $operator->syncRoles([RoleEnum::ClientOperator->value]);

    $this->actingAs($operator)
        ->post(route('team.store'), [
            'email' => 'teammate@example.com',
            'role' => RoleEnum::ClientOperator->value,
        ])
        ->assertForbidden();

    expect(UserInvitation::query()->count())->toBe(0);
});

it('allows pending clients to manage their team while awaiting activation', function (): void {
    [$client, , $clientAdmin] = clientWithWallet();
    $client->update(['status' => ClientStatus::Pending]);

    $this->actingAs($clientAdmin)
        ->get(route('team.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.invite', true));

    $this->actingAs($clientAdmin)
        ->post(route('team.store'), [
            'email' => 'teammate@example.com',
            'role' => RoleEnum::ClientOperator->value,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(UserInvitation::query()->where('client_id', $client->id)->count())->toBe(1);
});

it('lists only the organisation team', function () {
    [$client, , $clientAdmin] = clientWithWallet();
    User::factory()->forClient($client)->count(2)->create();
    User::factory()->create();

    $this->actingAs($clientAdmin)
        ->get(route('team.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/team')
            ->where('stats.members', 3)
            ->where('can.invite', true));
});

it('forbids platform users from the client team screen', function () {
    $admin = platformUser();

    $this->actingAs($admin)
        ->get(route('team.index'))
        ->assertForbidden();
});
