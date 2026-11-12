<?php

use App\Enums\Role;
use App\Enums\WhatsAppProviderResultStatus;
use App\Models\Client;
use App\Models\User;
use App\Models\WhatsAppClientConnector;
use App\Models\WhatsAppConnector;
use App\Models\WhatsAppProvider;
use App\Services\WhatsApp\ProPlusWhatsAppProvider;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

it('does not send when WhatsApp sending is disabled', function (): void {
    Http::preventStrayRequests();
    config(['whatsapp.sending_enabled' => false]);
    $provider = WhatsAppProvider::factory()->create(['is_enabled' => true]);

    $response = app(ProPlusWhatsAppProvider::class)->send($provider, ['OTP' => '123456']);

    expect($response->status)->toBe(WhatsAppProviderResultStatus::Unknown);
    Http::assertNothingSent();
});

it('does not treat an HTTP success without a provider message reference as accepted delivery', function (): void {
    Http::fake([
        'sandbox.example.test/api/whatsapp/v1/Send' => Http::response(['status' => 'queued'], 200),
    ]);
    config(['whatsapp.sending_enabled' => true]);
    configureWhatsAppEnvironment();
    $provider = WhatsAppProvider::factory()->create(['is_enabled' => true]);

    $response = app(ProPlusWhatsAppProvider::class)->send($provider, ['OTP' => '123456']);

    expect($response->status)->toBe(WhatsAppProviderResultStatus::Unknown);
});

it('recognizes the documented bulk send response and extracts its nested message reference', function (): void {
    Http::fake([
        'sandbox.example.test/api/whatsapp/v1/Send' => Http::response([
            'statusCode' => 0,
            'status' => 'Success',
            'messages' => [['messageId' => 'provider-message-123', 'receiverNumber' => '15551234567']],
        ], 200),
    ]);
    config(['whatsapp.sending_enabled' => true]);
    configureWhatsAppEnvironment();
    $provider = WhatsAppProvider::factory()->create(['is_enabled' => true]);

    $response = app(ProPlusWhatsAppProvider::class)->send($provider, ['SenderNumber' => '15550000000']);

    expect($response->status)->toBe(WhatsAppProviderResultStatus::Accepted)
        ->and($response->providerReference)->toBe('provider-message-123')
        ->and($response->data['messages'][0]['messageId'])->toBe('provider-message-123');
});

it('uses the shared environment endpoint and basic authentication for provider calls', function (): void {
    Http::fake([
        'sandbox.example.test/custom/connectors' => Http::response(['connectors' => []], 200),
    ]);
    configureWhatsAppEnvironment();
    config(['whatsapp.provider.endpoints.connectors' => '/custom/connectors']);
    $provider = WhatsAppProvider::factory()->create();

    $response = app(ProPlusWhatsAppProvider::class)->getConnectors($provider);

    expect($response->httpStatus)->toBe(200);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://sandbox.example.test/custom/connectors'
        && $request->hasHeader('Authorization'));
});

it('redacts credential and OTP values from normalized provider responses', function (): void {
    Http::fake([
        'sandbox.example.test/api/whatsapp/v1/Connector/GetAll' => Http::response([
            'connectors' => [['id' => 'connector-1', 'apiKey' => 'secret', 'OTP' => '123456']],
        ], 200),
    ]);
    configureWhatsAppEnvironment();
    $provider = WhatsAppProvider::factory()->create();

    $response = app(ProPlusWhatsAppProvider::class)->getConnectors($provider);

    expect($response->data['connectors'][0]['apiKey'])->toBe('[REDACTED]')
        ->and($response->data['connectors'][0]['OTP'])->toBe('[REDACTED]');
});

it('isolates connector assignments between clients', function (): void {
    $client = Client::factory()->create();
    $otherClient = Client::factory()->create();
    $user = userWithRole(Role::ClientAdmin, $client);
    $assignment = WhatsAppClientConnector::factory()->create(['client_id' => $otherClient->id]);

    expect($user->can('view', $assignment))->toBeFalse();
});

it('allows a client administrator to view only their own connector assignments', function (): void {
    $client = Client::factory()->create();
    $user = userWithRole(Role::ClientAdmin, $client);
    $connector = WhatsAppConnector::factory()->create();
    $assignment = WhatsAppClientConnector::factory()->create([
        'client_id' => $client->id,
        'connector_id' => $connector->id,
    ]);

    expect($user->can('view', $assignment))->toBeTrue();
});

it('lets platform administrators configure a provider while shared secrets stay in environment config', function (): void {
    $administrator = platformUser();

    $this->actingAs($administrator)
        ->post('/admin/whatsapp/providers', [
            'name' => 'Pro Plus Sandbox',
            'driver' => 'pro_plus',
            'is_enabled' => true,
            'connect_timeout' => 5,
            'request_timeout' => 20,
        ])
        ->assertRedirect();

    $provider = WhatsAppProvider::query()->where('name', 'Pro Plus Sandbox')->firstOrFail();
    $audit = $provider->auditLogs()->firstOrFail();

    expect($audit->properties)->not->toHaveKey('client_id')
        ->and($audit->properties)->not->toHaveKey('api_key');

    config([
        'whatsapp.provider.base_url' => null,
        'whatsapp.provider.client_id' => null,
        'whatsapp.provider.api_key' => null,
    ]);

    $this->actingAs($administrator)
        ->withoutVite()
        ->get('/admin/whatsapp/providers')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/whatsapp/providers/index')
            ->where('providerConfiguration.credentialsConfigured', false)
            ->missing('providers.0.base_url')
            ->missing('providers.0.client_id')
            ->missing('providers.0.api_key'));
});

function configureWhatsAppEnvironment(): void
{
    config([
        'whatsapp.provider.base_url' => 'https://sandbox.example.test',
        'whatsapp.provider.client_id' => 'environment-client',
        'whatsapp.provider.api_key' => 'environment-secret',
        'whatsapp.provider.endpoints.connectors' => '/api/whatsapp/v1/Connector/GetAll',
        'whatsapp.provider.endpoints.templates' => '/api/whatsapp/v1/Template/GetAll',
        'whatsapp.provider.endpoints.send' => '/api/whatsapp/v1/Send',
    ]);
}

it('denies provider administration to tenant users', function (): void {
    [, , $clientAdmin] = clientWithWallet();

    $this->actingAs($clientAdmin)
        ->get('/admin/whatsapp/providers')
        ->assertForbidden();
});

function userWithRole(Role $role, Client $client): User
{
    $user = User::factory()->for($client)->create();
    $user->syncRoles([$role->value]);

    return $user;
}
