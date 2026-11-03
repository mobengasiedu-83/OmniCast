<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\RecordsActivity;
use App\Enums\Role as RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\SuspendUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\Client;
use App\Models\User;
use App\Services\SessionMonitor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The platform user directory: staff accounts, roles, suspension.
 */
class UserController extends Controller
{
    use RecordsActivity;

    /**
     * List every account on the platform.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'role' => $request->string('role')->toString(),
            'status' => $request->string('status')->toString(),
            'client_id' => $request->string('client_id')->toString(),
        ];

        /** @var LengthAwarePaginator<int, array<string, mixed>> $users */
        $users = User::query()
            ->with(['client:id,name', 'roles:id,name'])
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $term = '%'.$filters['search'].'%';

                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('job_title', 'like', $term);
                });
            })
            ->when($filters['role'] !== '', fn ($query) => $query->whereHas(
                'roles',
                fn ($query) => $query->where('name', $filters['role']),
            ))
            ->when($filters['status'] === 'active', fn ($query) => $query
                ->where('is_active', true)
                ->whereNull('suspended_at'))
            ->when($filters['status'] === 'suspended', fn ($query) => $query
                ->where(fn ($query) => $query
                    ->where('is_active', false)
                    ->orWhereNotNull('suspended_at')))
            ->when($filters['status'] === 'unverified', fn ($query) => $query->whereNull('email_verified_at'))
            ->when($filters['client_id'] !== '', fn ($query) => $query->where('client_id', (int) $filters['client_id']))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user): array => $this->row($user));

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => $filters,
            'stats' => [
                'total' => User::query()->count(),
                'staff' => User::query()->whereNull('client_id')->count(),
                'active' => User::query()->where('is_active', true)->whereNull('suspended_at')->count(),
                'suspended' => User::query()
                    ->where(fn ($query) => $query->where('is_active', false)->orWhereNotNull('suspended_at'))
                    ->count(),
                'mfa' => User::query()->whereNotNull('two_factor_confirmed_at')->count(),
                'unverified' => User::query()->whereNull('email_verified_at')->count(),
            ],
            'roles' => $this->roleOptions(),
            'statuses' => [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'suspended', 'label' => 'Suspended'],
                ['value' => 'unverified', 'label' => 'Unverified email'],
            ],
            'clients' => Client::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Client $client): array => ['id' => $client->id, 'name' => $client->name])
                ->values()
                ->all(),
            'can' => [
                'create' => Gate::allows('create', User::class),
            ],
        ]);
    }

    /**
     * Create a staff or client account.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'job_title' => $validated['job_title'] ?? null,
                'client_id' => $validated['client_id'] ?? null,
                'is_active' => true,
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();
            $user->syncRoles([$validated['role']]);

            $this->recordActivity('created user', $user, [
                'role' => $validated['role'],
                'client_id' => $user->client_id,
            ]);

            return $user;
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$user->name} can now sign in.",
        ]);
    }

    /**
     * Update an account's details and role.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($user, $validated): void {
            $previousRoles = $user->getRoleNames()->all();

            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'job_title' => $validated['job_title'] ?? null,
                'client_id' => $validated['client_id'] ?? null,
            ]);

            $user->syncRoles([$validated['role']]);

            $this->recordActivity('updated user', $user, [
                'previous_roles' => $previousRoles,
                'role' => $validated['role'],
                'client_id' => $user->client_id,
            ]);
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$user->name} updated.",
        ]);
    }

    /**
     * Suspend an account and end its live sessions.
     */
    public function suspend(SuspendUserRequest $request, User $user): RedirectResponse
    {
        $reason = (string) $request->validated('reason');

        $user->suspend($reason);

        app(SessionMonitor::class)->revokeForUser($user->id);

        $this->recordActivity('suspended user', $user, ['reason' => $reason]);

        return back()->with('toast', [
            'type' => 'warning',
            'message' => "{$user->name} suspended and signed out everywhere.",
        ]);
    }

    /**
     * Reactivate a suspended account.
     */
    public function reactivate(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('reactivate', $user);

        $user->reactivate();

        $this->recordActivity('reactivated user', $user);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$user->name} reactivated.",
        ]);
    }

    /**
     * Present an account for the directory table.
     *
     * @return array<string, mixed>
     */
    protected function row(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'job_title' => $user->job_title,
            'client_id' => $user->client_id,
            'client' => $user->client?->name,
            'roles' => $user->getRoleNames()
                ->map(fn (string $name): array => [
                    'name' => $name,
                    'label' => RoleEnum::tryFrom($name)?->label() ?? Str::headline($name),
                ])
                ->values()
                ->all(),
            'is_active' => $user->isActive(),
            'is_suspended' => $user->isSuspended(),
            'suspension_reason' => $user->suspension_reason,
            'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
            'email_verified' => $user->email_verified_at !== null,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    /**
     * The roles an administrator may assign, with their labels.
     *
     * @return list<array{value: string, label: string}>
     */
    protected function roleOptions(): array
    {
        return array_map(
            fn (RoleEnum $role): array => ['value' => $role->value, 'label' => $role->label()],
            RoleEnum::cases(),
        );
    }
}
