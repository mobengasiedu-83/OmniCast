<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\ModuleAvailability;
use App\Services\PlatformConfiguration;
use App\Services\TurnstileVerifier;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\App;
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
        $clientSettings = $account?->client?->settings ?? [];
        $locale = $clientSettings['locale'] ?? config('app.locale');
        App::setLocale(in_array($locale, config('app.supported_locales', ['en']), true) ? $locale : config('app.locale'));
        $configuration = app(PlatformConfiguration::class);

        return [
            ...parent::share($request),
            'name' => $configuration->resolve('company', 'name', config('app.name')),
            'clientBranding' => [
                'name' => $clientSettings['branding']['name'] ?? null,
                'primaryColor' => $clientSettings['branding']['primary_color'] ?? null,
            ],
            'auth' => [
                'user' => $account,
                'roles' => fn (): array => $account?->getRoleNames()->values()->all() ?? [],
                'permissions' => fn (): array => $account?->getAllPermissions()->pluck('name')->values()->all() ?? [],
                'isPlatformUser' => fn (): bool => $account?->isPlatformUser() ?? false,
                'clientId' => fn (): ?int => $account?->client_id,
                'clientName' => fn (): ?string => $account?->client?->name,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'modules' => fn (): array => app(ModuleAvailability::class)->forClient($account?->client),
            'turnstile' => function (): array {
                $turnstile = app(TurnstileVerifier::class);

                return [
                    'enabled' => $turnstile->enabled(),
                    'site_key' => $turnstile->siteKey(),
                ];
            },
            'notificationCentre' => fn (): ?array => $account === null ? null : [
                'unread_count' => $account->notifications()->whereNull('read_at')->count(),
                'items' => $account->notifications()
                    ->whereNull('read_at')
                    ->orderByDesc('created_at')
                    ->limit(max((int) config('notifications.inbox.bell_items', 6), 1))
                    ->get()
                    ->map(fn (DatabaseNotification $notification): array => [
                        'id' => $notification->id,
                        'title' => (string) data_get($notification->data, 'title', 'Notification'),
                        'body' => (string) data_get($notification->data, 'body', ''),
                        'category' => (string) data_get($notification->data, 'category', 'system'),
                        'severity' => (string) data_get($notification->data, 'severity', 'info'),
                        'action_url' => data_get($notification->data, 'action_url'),
                        'created_at' => $notification->created_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
                'index_url' => route('notifications.index'),
                'read_all_url' => route('notifications.read-all'),
            ],
            'flash' => [
                'toast' => fn (): mixed => $request->session()->get('toast'),
                'import' => fn (): mixed => $request->session()->get('import'),
            ],
        ];
    }
}
