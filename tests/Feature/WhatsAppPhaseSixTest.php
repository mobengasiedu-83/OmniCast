<?php

use App\Enums\WhatsAppConnectorStatus;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Wallet;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppConnector;
use App\Models\WhatsAppProvider;
use App\Models\WhatsAppProviderEvent;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppUsageRecord;
use App\Services\WhatsApp\WhatsAppInvoiceReconciliationService;
use App\Services\WhatsApp\WhatsAppProviderEventIngestor;
use App\Services\WhatsApp\WhatsAppWalletService;

function phaseSixCampaign(): array
{
    $client = Client::factory()->create();
    Wallet::factory()->for($client)->create(['balance' => 500, 'reserved_balance' => 0]);
    $provider = WhatsAppProvider::factory()->create(['is_enabled' => true]);
    $connector = WhatsAppConnector::factory()->create(['provider_id' => $provider->id, 'status' => WhatsAppConnectorStatus::Available]);
    $template = WhatsAppTemplate::factory()->create(['connector_id' => $connector->id]);
    $contact = Contact::factory()->forClient($client)->create();
    $campaign = WhatsAppCampaign::query()->create([
        'client_id' => $client->id,
        'connector_id' => $connector->id,
        'template_id' => $template->id,
        'name' => 'Phase six evidence',
        'status' => 'sending',
        'control_state' => 'active',
        'schedule_type' => 'now',
        'timezone' => 'Africa/Accra',
        'estimated_cost' => 0.15,
        'price_snapshot' => ['currency' => 'GHS'],
    ]);
    $recipient = WhatsAppCampaignRecipient::query()->create([
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'phone' => $contact->phone,
        'eligibility' => 'eligible',
        'snapshot_json' => [],
        'status' => 'accepted',
        'provider_message_id' => 'provider-message-1',
        'unit_price' => 0.15,
        'unit_price_snapshot' => [
            'rate_id' => 9,
            'category' => 'marketing',
            'market' => 'GH',
            'meta_rate' => '0.020000',
            'provider_fee' => '0.000000',
            'direct_cost' => '0.000000',
            'fx_rate' => '10.000000',
            'selling_price' => '0.1500',
        ],
    ]);
    app(WhatsAppWalletService::class)->reserve($campaign);

    return [$provider, $campaign->fresh(), $recipient->fresh()];
}

it('accepts only signed provider events after the event contract is enabled', function (): void {
    [$provider] = phaseSixCampaign();
    config(['whatsapp.events.contract_enabled' => false]);
    $payload = ['event_id' => 'evt-disabled', 'message_id' => 'provider-message-1', 'event_type' => 'status', 'status' => 'delivered'];
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    $this->call('POST', route('webhooks.whatsapp.pro-plus'), [], [], [], ['CONTENT_TYPE' => 'application/json'], $raw)->assertNotFound();

    config(['whatsapp.events.contract_enabled' => true, 'whatsapp.events.provider_id' => $provider->id, 'whatsapp.events.signing_secret' => 'secret-for-test']);
    $this->call('POST', route('webhooks.whatsapp.pro-plus'), [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_X_WHATSAPP_SIGNATURE' => 'sha256=invalid',
    ], $raw)->assertUnauthorized();

    expect(WhatsAppProviderEvent::query()->count())->toBe(0);

    $signature = 'sha256='.hash_hmac('sha256', $raw, 'secret-for-test');
    $this->call('POST', route('webhooks.whatsapp.pro-plus'), [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_X_WHATSAPP_SIGNATURE' => $signature,
    ], $raw)->assertStatus(202);

    expect(WhatsAppProviderEvent::query()->firstOrFail()->signature_verified)->toBeTrue();
});

it('deduplicates verified billable events, settles once, and prevents status regression', function (): void {
    [$provider, $campaign, $recipient] = phaseSixCampaign();
    $ingestor = app(WhatsAppProviderEventIngestor::class);
    $payload = ['event_id' => 'evt-delivered', 'message_id' => 'provider-message-1', 'event_type' => 'delivery', 'status' => 'delivered', 'billable' => true];

    $first = $ingestor->ingest($provider, $payload, true);
    $duplicate = $ingestor->ingest($provider, $payload, true);
    $ingestor->ingest($provider, ['event_id' => 'evt-read', 'message_id' => 'provider-message-1', 'event_type' => 'read', 'status' => 'read'], true);
    $ingestor->ingest($provider, ['event_id' => 'evt-late-sent', 'message_id' => 'provider-message-1', 'event_type' => 'status', 'status' => 'sent'], true);

    expect($first->id)->toBe($duplicate->id)
        ->and($recipient->fresh()->status)->toBe('read')
        ->and(WhatsAppProviderEvent::query()->where('provider_message_id', 'provider-message-1')->count())->toBe(3)
        ->and(WhatsAppUsageRecord::query()->where('provider_message_id', 'provider-message-1')->count())->toBe(1)
        ->and(WhatsAppUsageRecord::query()->where('provider_message_id', 'provider-message-1')->value('market'))->toBe('GH')
        ->and(WhatsAppUsageRecord::query()->where('provider_message_id', 'provider-message-1')->value('category'))->toBe('marketing')
        ->and(Wallet::query()->where('client_id', $campaign->client_id)->firstOrFail()->fresh()->balance)->toBe('499.8500')
        ->and($campaign->walletReservation()->firstOrFail()->fresh()->captured_amount)->toBe('0.1500');
});

it('records invoice cost variances for review without hiding unmatched usage', function (): void {
    [$provider] = phaseSixCampaign();
    WhatsAppUsageRecord::query()->create([
        'provider_id' => $provider->id,
        'provider_message_id' => 'provider-message-1',
        'provider_usage_id' => 'usage-1',
        'quantity' => 1,
        'amount' => 0.15,
        'internal_cost' => 0.20,
        'currency' => 'GHS',
        'evidence' => ['source' => 'verified event'],
    ]);
    $invoice = app(WhatsAppInvoiceReconciliationService::class)->import($provider, [
        'invoice_number' => 'INV-2026-01',
        'period_start' => '2026-11-01',
        'period_end' => '2026-11-30',
        'currency' => 'GHS',
        'total_amount' => 0.50,
    ], [
        ['reference' => 'usage-1', 'amount' => 0.25],
        ['reference' => 'usage-not-found', 'amount' => 0.25],
    ]);

    expect($invoice->status)->toBe('review_required')
        ->and($invoice->reconciliations()->where('provider_reference', 'usage-1')->firstOrFail()->variance)->toBe('0.0500')
        ->and($invoice->reconciliations()->where('provider_reference', 'usage-1')->firstOrFail()->status)->toBe('review_required')
        ->and($invoice->reconciliations()->where('provider_reference', 'usage-not-found')->firstOrFail()->details['reason'])->toContain('No internal provider usage')
        ->and($invoice->reconciliations()->where('provider_reference', 'invoice-total:INV-2026-01')->exists())->toBeTrue();
});
