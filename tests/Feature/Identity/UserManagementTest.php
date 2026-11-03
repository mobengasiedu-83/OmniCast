<?php

use App\Enums\LoginEvent;
use App\Enums\Role as RoleEnum;
use App\Models\Client;
use App\Models\LoginAudit;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('lists every account for platform administrators', function () {
    $admin = platformUser();
    User::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/index')
            ->where('users.total', 4)
            ->has('roles', count(RoleEnum::cases())));
});

it('forbids client users from the user directory', function () {
    $client = Client::factory()->create();
    $clientAdmin = User::factory()->forClient($client)->create();
    $clientAdmin->syncRoles([RoleEnum::ClientAdmin->value]);

    $this->actingAs($clientAdmin)
        ->get(route('admin.users.index'))
        ->assertForbidden();

    $this->actingAs($clientAdmin)
        ->post(route('admin.users.store'), [
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
            'role' => RoleEnum::SuperAdmin->value,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertForbidden();
});

it('creates a staff account with the chosen role', function () {
    $admin = platformUser();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Akosua Mensah',
            'email' => 'Akosua.Mensah@Example.com',
            'job_title' => 'Compliance lead',
            'role' => RoleEnum::ComplianceOfficer->value,
            'client_id' => '',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'akosua.mensah@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user?->hasRole(RoleEnum::ComplianceOfficer->value))->toBeTrue()
        ->and($user?->client_id)->toBeNull()
        ->and($user?->email_verified_at)->not->toBeNull();
});

it('rejects a client role without an organisation', function () {
    $admin = platformUser();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'No Tenant',
            'email' => 'no-tenant@example.com',
            'role' => RoleEnum::ClientOperator->value,
            'client_id' => '',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertSessionHasErrors('client_id');

    expect(User::query()->where('email', 'no-tenant@example.com')->exists())->toBeFalse();
});

it('suspends an account and blocks the next sign in', function () {
    $admin = platformUser();
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.suspend', $target), ['reason' => 'Chargeback fraud'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $target->refresh();

    expect($target->isSuspended())->toBeTrue()
        ->and($target->is_active)->toBeFalse();

    $this->post(route('logout'));
    $this->assertGuest();

    $this->post(route('login.store'), [
        'email' => $target->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();

    expect(LoginAudit::query()->where('event', LoginEvent::LoginBlocked->value)->count())->toBe(1);
});

it('reactivates a suspended account', function () {
    $admin = platformUser();
    $target = User::factory()->create();
    $target->suspend('Abuse');

    $this->actingAs($admin)
        ->post(route('admin.users.reactivate', $target))
        ->assertRedirect();

    expect($target->fresh()?->isActive())->toBeTrue();
});

it('prevents an administrator from suspending their own account', function () {
    $admin = platformUser();

    $this->actingAs($admin)
        ->post(route('admin.users.suspend', $admin), ['reason' => 'Testing'])
        ->assertForbidden();

    expect($admin->fresh()?->isSuspended())->toBeFalse();
});

it('prevents a platform user from suspending a super admin', function () {
    $actor = platformUser(RoleEnum::ComplianceOfficer);
    $actor->givePermissionTo('users.manage');

    $target = platformUser();

    $this->actingAs($actor)
        ->post(route('admin.users.suspend', $target), ['reason' => 'Testing'])
        ->assertForbidden();

    expect($target->fresh()?->isSuspended())->toBeFalse();
});
