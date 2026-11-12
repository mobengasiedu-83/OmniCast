<?php

use App\Enums\Role;
use App\Enums\WhatsAppProviderResultStatus;
use App\Enums\WhatsAppTemplateStatus;
use App\Models\Client;
use App\Models\User;
use App\Models\WhatsAppConnector;
use App\Models\WhatsAppMediaAsset;
use App\Models\WhatsAppProvider;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\ProPlusWhatsAppProvider;
use App\Services\WhatsApp\WhatsAppCatalogSync;
use App\Services\WhatsApp\WhatsAppMediaLibrary;
use App\Services\WhatsApp\WhatsAppTemplatePayloadValidator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

it('synchronizes provider connectors using the configured shared endpoint', function (): void {
    Http::fake([
        'sandbox.example.test/api/whatsapp/v1/Connector/GetAll' => Http::response([[
            'connectorName' => 'Example WA',
            'phoneNumber' => '15550001111',
            'phoneNumberId' => 'phone-1',
            'whatsappBusinessAccountId' => 'waba-1',
        ]], 200),
    ]);
    configurePhaseTwoWhatsAppEnvironment();
    $provider = WhatsAppProvider::factory()->create(['is_enabled' => true]);

    $result = app(WhatsAppCatalogSync::class)->syncConnectors($provider);

    expect($result['failed'])->toBeFalse()
        ->and($result['created'])->toBe(1)
        ->and(WhatsAppConnector::query()->where('external_id', 'phone-1')->value('waba_id'))->toBe('waba-1');
});

it('treats provider application errors as failures even when the HTTP response is successful', function (): void {
    Http::fake([
        'sandbox.example.test/api/whatsapp/v1/Connector/GetAll' => Http::response(['statusCode' => 1002, 'status' => 'Error'], 200),
    ]);
    configurePhaseTwoWhatsAppEnvironment();
    $provider = WhatsAppProvider::factory()->create();

    $result = app(ProPlusWhatsAppProvider::class)->getConnectors($provider);

    expect($result->httpStatus)->toBe(200)->and($result->status)->toBe(WhatsAppProviderResultStatus::Rejected);
});

it('synchronizes template metadata and records immutable versions without interpreting numeric statuses', function (): void {
    Http::fake([
        'sandbox.example.test/api/whatsapp/v1/Template/GetAll*' => Http::response([
            'templates' => [[
                'metaTemplateId' => 'meta-1',
                'name' => 'welcome',
                'language' => 'en_US',
                'category' => 'UTILITY',
                'status' => 'APPROVED',
            ], [
                'metaTemplateId' => 'meta-2',
                'name' => 'legacy',
                'language' => 'en',
                'status' => '2',
            ]],
        ], 200),
        'sandbox.example.test/api/whatsapp/v1/Template/Get*' => Http::response(['components' => [['type' => 'BODY', 'text' => 'Hello {{1}}']]], 200),
    ]);
    configurePhaseTwoWhatsAppEnvironment();
    $connector = WhatsAppConnector::factory()->create(['waba_id' => 'waba-1']);

    $result = app(WhatsAppCatalogSync::class)->syncTemplates($connector);
    $approved = WhatsAppTemplate::query()->where('meta_template_id', 'meta-1')->firstOrFail();
    $unknown = WhatsAppTemplate::query()->where('meta_template_id', 'meta-2')->firstOrFail();

    expect($result['created'])->toBe(2)
        ->and($approved->status)->toBe(WhatsAppTemplateStatus::Approved)
        ->and($approved->versions()->count())->toBe(1)
        ->and($unknown->status)->toBe(WhatsAppTemplateStatus::Unknown);
});

it('rejects payload validation for unapproved templates and missing variables', function (): void {
    $template = WhatsAppTemplate::factory()->create([
        'status' => WhatsAppTemplateStatus::Pending,
        'components' => [['type' => 'BODY', 'text' => 'Hello {{1}} and {{2}}']],
    ]);

    $errors = app(WhatsAppTemplatePayloadValidator::class)->validate($template, [
        'SenderNumber' => '+15550000000',
        'MetaTemplateId' => $template->meta_template_id,
        'BulkPayload' => ['RecieverNumber' => '+15551111111', 'BodyVariables' => ['A']],
    ]);

    expect($errors)->toHaveKey('template')->and($errors)->toHaveKey('BulkPayload.BodyVariables');
});

it('serves media through a signed response and rejects an invalid signature', function (): void {
    Storage::fake('local');
    $path = 'whatsapp/client/file.pdf';
    Storage::disk('local')->put($path, '%PDF-1.4 fake');
    $asset = WhatsAppMediaAsset::factory()->create([
        'storage_disk' => 'local',
        'path' => $path,
        'mime_type' => 'application/pdf',
    ]);
    config(['app.url' => 'https://omnicast.example.test']);
    $url = app(WhatsAppMediaLibrary::class)->temporaryHttpsUrl($asset);

    $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('whatsapp.media.show', ['asset' => $asset->id, 'signature' => 'invalid', 'expires' => now()->addMinute()->timestamp]))->assertForbidden();
    $expiredUrl = URL::temporarySignedRoute('whatsapp.media.show', now()->subMinute(), ['asset' => $asset->id], absolute: true);
    $this->get($expiredUrl)->assertForbidden();
});

it('does not issue temporary media URLs across client tenants', function (): void {
    $client = Client::factory()->create();
    $otherClient = Client::factory()->create();
    $user = User::factory()->forClient($client)->create();
    $user->syncRoles([Role::ClientAdmin->value]);
    $asset = WhatsAppMediaAsset::factory()->create(['client_id' => $otherClient->id]);

    $this->actingAs($user)->get(route('whatsapp.media.url', $asset))->assertNotFound();
});

function configurePhaseTwoWhatsAppEnvironment(): void
{
    config([
        'whatsapp.provider.base_url' => 'https://sandbox.example.test',
        'whatsapp.provider.client_id' => 'environment-client',
        'whatsapp.provider.api_key' => 'environment-secret',
        'whatsapp.provider.endpoints.connectors' => '/api/whatsapp/v1/Connector/GetAll',
        'whatsapp.provider.endpoints.templates' => '/api/whatsapp/v1/Template/GetAll',
        'whatsapp.provider.endpoints.template' => '/api/whatsapp/v1/Template/Get',
    ]);
}
