<?php

use App\Enums\Role as RoleEnum;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Register a live session row for the given user.
 */
function identitySession(User $user, string $id): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'ip_address' => '10.0.0.1',
        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36',
        'payload' => base64_encode('payload'),
        'last_activity' => now()->getTimestamp(),
    ]);
}

beforeEach(function (): void {
    config(['session.driver' => 'database']);
});

it('lists only the sessions of the signed-in account', function () {
    $user = User::factory()->create();
    identitySession($user, 'own-session');
    identitySession(User::factory()->create(), 'other-session');

    $this->actingAs($user)
        ->get(route('account.sessions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/sessions')
            ->has('sessions', 1));
});

it('ends another session of the same account', function () {
    $user = User::factory()->create();
    identitySession($user, 'first-session');
    identitySession($user, 'second-session');

    $this->actingAs($user)
        ->delete(route('account.sessions.destroy', 'second-session'))
        ->assertRedirect();

    expect(DB::table('sessions')->where('id', 'second-session')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'first-session')->exists())->toBeTrue();
});

it('does not end a session belonging to another account', function () {
    $user = User::factory()->create();
    $victim = User::factory()->create();
    identitySession($victim, 'victim-session');

    $this->actingAs($user)
        ->delete(route('account.sessions.destroy', 'victim-session'))
        ->assertRedirect();

    expect(DB::table('sessions')->where('id', 'victim-session')->exists())->toBeTrue();
});

it('signs the account out of all other sessions', function () {
    $user = User::factory()->create();
    identitySession($user, 'session-one');
    identitySession($user, 'session-two');

    $this->actingAs($user)
        ->delete(route('account.sessions.destroy-others'))
        ->assertRedirect();

    expect(DB::table('sessions')->whereIn('id', ['session-one', 'session-two'])->count())->toBe(0);
});

it('lets platform administrators end a user session', function () {
    $admin = platformUser();
    $target = User::factory()->create();
    identitySession($target, 'target-session');

    $this->actingAs($admin)
        ->delete(route('admin.sessions.destroy-one', [
            'user' => $target->id,
            'session' => 'target-session',
        ]))
        ->assertRedirect();

    expect(DB::table('sessions')->where('id', 'target-session')->exists())->toBeFalse();
});

it('forbids client users from the platform session monitor', function () {
    $client = Client::factory()->create();
    $clientAdmin = User::factory()->forClient($client)->create();
    $clientAdmin->syncRoles([RoleEnum::ClientAdmin->value]);

    $this->actingAs($clientAdmin)
        ->get(route('admin.sessions.index'))
        ->assertForbidden();
});
