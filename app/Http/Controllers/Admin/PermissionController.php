<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * A read-only catalogue of every ability the platform recognises.
 */
class PermissionController extends Controller
{
    /**
     * List the permissions and the roles that hold them.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', RoleModel::class);

        /** @var array<string, list<string>> $holders */
        $holders = [];

        foreach (RoleModel::query()->with('permissions')->get() as $role) {
            $label = RoleEnum::tryFrom($role->name)?->label() ?? Str::headline($role->name);

            foreach ($role->getPermissionNames() as $permission) {
                $holders[$permission][] = $label;
            }
        }

        /** @var array<string, array{key: string, label: string, permissions: list<array{value: string, label: string, roles: list<string>}>}> $groups */
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
                'roles' => $holders[$permission->value] ?? [],
            ];
        }

        return Inertia::render('admin/users/permissions', [
            'groups' => array_values($groups),
            'roles' => RoleModel::query()->orderBy('name')->pluck('name')
                ->map(fn (string $name): array => [
                    'name' => $name,
                    'label' => RoleEnum::tryFrom($name)?->label() ?? Str::headline($name),
                ])
                ->values()
                ->all(),
        ]);
    }
}
