<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\RecordsActivity;
use App\Contracts\WhatsApp\WhatsAppProvider as WhatsAppProviderContract;
use App\Data\WhatsApp\WhatsAppProviderResponse;
use App\Enums\WhatsAppProviderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWhatsAppProviderRequest;
use App\Http\Requests\Admin\UpdateWhatsAppProviderRequest;
use App\Models\WhatsAppProvider;
use App\Services\WhatsApp\WhatsAppAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppProviderController extends Controller
{
    use RecordsActivity;

    public function index(): Response
    {
        Gate::authorize('viewAny', WhatsAppProvider::class);

        $providers = WhatsAppProvider::query()
            ->orderBy('name')
            ->get()
            ->map(fn (WhatsAppProvider $provider): array => [
                'id' => $provider->id,
                'name' => $provider->name,
                'driver' => $provider->driver,
                'status' => $provider->status->value,
                'status_label' => $provider->status->label(),
                'is_enabled' => $provider->is_enabled,
                'connect_timeout' => $provider->connect_timeout,
                'request_timeout' => $provider->request_timeout,
                'tested_at' => $provider->tested_at?->toIso8601String(),
                'last_test_error' => $provider->last_test_error,
            ])
            ->values()
            ->all();

        return Inertia::render('admin/whatsapp/providers/index', [
            'providers' => $providers,
            'can' => [
                'manage' => Gate::allows('create', WhatsAppProvider::class),
                'test' => auth()->user()?->can('whatsapp.providers.test') ?? false,
            ],
            'sendingEnabled' => (bool) config('whatsapp.sending_enabled', false),
            'providerConfiguration' => [
                'baseUrl' => config('whatsapp.provider.base_url'),
                'credentialsConfigured' => filled(config('whatsapp.provider.client_id')) && filled(config('whatsapp.provider.api_key')),
                'endpointsConfigured' => collect(config('whatsapp.provider.endpoints', []))->every(fn (mixed $endpoint): bool => filled($endpoint)),
            ],
        ]);
    }

    public function store(StoreWhatsAppProviderRequest $request, WhatsAppAuditLogger $audit): RedirectResponse
    {
        $provider = WhatsAppProvider::query()->create([
            ...$request->validated(),
            'status' => $this->environmentConfigured() ? WhatsAppProviderStatus::Configured : WhatsAppProviderStatus::Unconfigured,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $audit->record('provider.created', $request->user(), null, $provider, [
            'name' => $provider->name,
            'driver' => $provider->driver,
            'is_enabled' => $provider->is_enabled,
        ]);

        $this->recordActivity('configured WhatsApp provider', $provider, [
            'provider_id' => $provider->id,
            'driver' => $provider->driver,
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'WhatsApp provider saved. Shared connection settings are read from the application environment.',
        ]);
    }

    public function update(
        UpdateWhatsAppProviderRequest $request,
        WhatsAppProvider $provider,
        WhatsAppAuditLogger $audit,
    ): RedirectResponse {
        $provider->fill([
            ...$request->validated(),
            'status' => $this->environmentConfigured() ? WhatsAppProviderStatus::Configured : WhatsAppProviderStatus::Unconfigured,
            'tested_at' => null,
            'last_test_error' => null,
            'updated_by' => $request->user()->id,
        ])->save();

        $audit->record('provider.updated', $request->user(), null, $provider, [
            'name' => $provider->name,
            'driver' => $provider->driver,
            'is_enabled' => $provider->is_enabled,
        ]);

        $this->recordActivity('updated WhatsApp provider configuration', $provider, [
            'provider_id' => $provider->id,
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'WhatsApp provider configuration saved.',
        ]);
    }

    public function testConnection(
        WhatsAppProvider $provider,
        WhatsAppProviderContract $whatsAppProvider,
        WhatsAppAuditLogger $audit,
    ): RedirectResponse {
        Gate::authorize('test', $provider);

        $result = $whatsAppProvider->testConnection($provider);
        $connected = $result->status->value === 'accepted';

        $provider->forceFill([
            'status' => $connected ? WhatsAppProviderStatus::Connected : WhatsAppProviderStatus::Failed,
            'tested_at' => now(),
            'last_test_error' => $connected ? null : $this->safeConnectionError($result),
        ])->save();

        $audit->record('provider.connection_tested', request()->user(), null, $provider, [
            'status' => $provider->status->value,
            'http_status' => $result->httpStatus,
            'error_category' => $result->errorCategory?->value,
        ]);

        $this->recordActivity('tested WhatsApp provider connection', $provider, [
            'provider_id' => $provider->id,
            'status' => $provider->status->value,
        ]);

        return back()->with('toast', [
            'type' => $connected ? 'success' : 'error',
            'message' => $connected
                ? 'The provider endpoint responded. Message delivery is not yet enabled.'
                : $this->safeConnectionError($result),
        ]);
    }

    protected function safeConnectionError(WhatsAppProviderResponse $result): string
    {
        return match ($result->errorCategory?->value) {
            'authentication' => 'The provider rejected the configured credentials.',
            'transport' => 'The provider endpoint could not be reached before the timeout.',
            'rate_limited' => 'The provider rate limited the connection check.',
            'validation' => 'The provider rejected the connection check request.',
            default => 'The provider connection could not be confirmed.',
        };
    }

    protected function environmentConfigured(): bool
    {
        return filled(config('whatsapp.provider.base_url'))
            && filled(config('whatsapp.provider.client_id'))
            && filled(config('whatsapp.provider.api_key'))
            && collect(config('whatsapp.provider.endpoints', []))->every(fn (mixed $endpoint): bool => filled($endpoint));
    }
}
