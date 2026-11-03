<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $account = $user instanceof User ? $user : null;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $account,
                'roles' => fn (): array => $account?->getRoleNames()->values()->all() ?? [],
                'permissions' => fn (): array => $account?->getAllPermissions()->pluck('name')->values()->all() ?? [],
                'isPlatformUser' => fn (): bool => $account?->isPlatformUser() ?? false,
                'clientId' => fn (): ?int => $account?->client_id,
                'clientName' => fn (): ?string => $account?->client?->name,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'toast' => fn (): mixed => $request->session()->get('toast'),
                'import' => fn (): mixed => $request->session()->get('import'),
            ],
        ];
    }
}
