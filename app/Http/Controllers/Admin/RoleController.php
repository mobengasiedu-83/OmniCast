<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\RecordsActivity;
use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\UpdateRolePermissionsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * The permission matrix: which abilities each role carries.
 */
class RoleController extends Controller
{
    use RecordsActivity;

    /**
     * Show the roles and their permissions.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', RoleModel::class);

        $roles = RoleModel::query()
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (RoleModel $role): array => [
                'name' => $role->name,
                'label' => RoleEnum::tryFrom($role->name)?->label() ?? Str::headline($role->name),
                'permissions' => $role->getPermissionNames()->values()->all(),
                'users_count' => $role->users()->count(),
                'locked' => $role->name === RoleEnum::SuperAdmin->value,
            ])
            ->values()
            ->all();

        return Inertia::render('admin/users/roles', [
            'roles' => $roles,
            'groups' => $this->permissionGroups(),
        ]);
    }

    /**
     * Replace the permissions granted by a role.
     */
    public function update(UpdateRolePermissionsRequest $request, RoleModel $role): RedirectResponse
    {
        $submitted = $request->validated('permissions');

        /** @var list<string> $permissions */
        $permissions = is_array($submitted)
            ? array_values(array_filter($submitted, 'is_string'))
            : [];

        DB::transaction(function () use ($role, $permissions): void {
            $role->syncPermissions($permissions);

            $this->recordActivity('updated role permissions', $role, [
                'role' => $role->name,
                'permissions' => $permissions,
            ]);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Permissions updated for {$role->name}.",
        ]);
    }

    /**
     * Group the permissions by the area they protect.
     *
     * @return list<array{key: string, label: string, permissions: list<array{value: string, label: string}>}>
     */
    protected function permissionGroups(): array
    {
        /** @var array<string, array{key: string, label: string, permissions: list<array{value: string, label: string}>}> $groups */
        $groups = [];

        foreach (PermissionEnum::cases() as $permission) {
            $key = Str::before($permission->value, '.');

            $groups[$key] ??= [
                'key' => $key,
                'label' => Str::headline($key),
                'permissions' => [],
            ];

            $groups[$key]['permissions'][] = [
                'value' => $permission->value,
                'label' => $permission->label(),
            ];
        }

        return array_values($groups);
    }
}
