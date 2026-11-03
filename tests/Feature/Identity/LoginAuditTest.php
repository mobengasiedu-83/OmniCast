<?php

use App\Enums\LoginEvent;
use App\Enums\Role as RoleEnum;
use App\Models\Client;
use App\Models\LoginAudit;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('records a successful sign in', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);

    $audit = LoginAudit::query()
        ->where('event', LoginEvent::LoginSucceeded->value)
        ->where('user_id', $user->id)
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit?->ip_address)->not->toBeNull()
        ->and($user->fresh()?->last_login_at)->not->toBeNull();
});

it('records a rejected sign in', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();

    expect(LoginAudit::query()
        ->where('event', LoginEvent::LoginFailed->value)
        ->where('email', $user->email)
        ->exists())->toBeTrue();
});

it('renders the audit trail for platform administrators', function () {
    $admin = platformUser();
    LoginAudit::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('admin.login-audit.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/login-audit')
            ->where('audits.total', 3)
            ->has('events', count(LoginEvent::cases())));
});

it('forbids client users from the audit trail', function () {
    $client = Client::factory()->create();
    $clientAdmin = User::factory()->forClient($client)->create();
    $clientAdmin->syncRoles([RoleEnum::ClientAdmin->value]);

    $this->actingAs($clientAdmin)
        ->get(route('admin.login-audit.index'))
        ->assertForbidden();
});
