<?php

use App\Enums\Permission;
use App\Enums\Role as RoleEnum;
use App\Models\Client;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

it('renders the permission matrix for super admins', function () {
    $admin = platformUser();

    $this->actingAs($admin)
        ->get(route('admin.roles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/roles')
            ->has('roles', count(RoleEnum::cases()))
            ->has('groups'));
});

it('updates the permissions granted by a role', function () {
    $admin = platformUser();

    $this->actingAs($admin)
        ->put(route('admin.roles.update', RoleEnum::ClientOperator->value), [
            'permissions' => ['contacts.view', 'campaigns.view'],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $role = Role::findByName(RoleEnum::ClientOperator->value);

    expect($role->getPermissionNames()->sort()->values()->all())
        ->toBe(['campaigns.view', 'contacts.view']);
});

it('forbids client users from editing role permissions', function () {
    $client = Client::factory()->create();
    $clientAdmin = User::factory()->forClient($client)->create();
    $clientAdmin->syncRoles([RoleEnum::ClientAdmin->value]);

    $this->actingAs($clientAdmin)
        ->put(route('admin.roles.update', RoleEnum::ClientOperator->value), [
            'permissions' => ['clients.manage'],
        ])
        ->assertForbidden();

    $role = Role::findByName(RoleEnum::ClientOperator->value);

    expect($role->hasPermissionTo('clients.manage'))->toBeFalse();
});

it('forbids platform officers from editing role permissions', function () {
    $actor = platformUser(RoleEnum::FinanceOfficer);

    $this->actingAs($actor)
        ->put(route('admin.roles.update', RoleEnum::ClientOperator->value), [
            'permissions' => [],
        ])
        ->assertForbidden();
});

it('leaves the super admin role locked', function () {
    $admin = platformUser();

    $this->actingAs($admin)
        ->put(route('admin.roles.update', RoleEnum::SuperAdmin->value), [
            'permissions' => ['reports.view'],
        ])
        ->assertForbidden();

    $role = Role::findByName(RoleEnum::SuperAdmin->value);

    expect($role->getPermissionNames())->toHaveCount(count(Permission::cases()));
});

it('lists every permission and the roles that hold it', function () {
    $admin = platformUser();

    $this->actingAs($admin)
        ->get(route('admin.permissions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/permissions')
            ->has('groups')
            ->has('roles', count(RoleEnum::cases())));
});
