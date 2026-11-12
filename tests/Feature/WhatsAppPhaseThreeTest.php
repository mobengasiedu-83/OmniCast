<?php

use App\Console\Commands\DispatchScheduledWhatsAppCampaigns;
use App\Contracts\WhatsApp\WhatsAppProvider as WhatsAppProviderContract;
use App\Data\WhatsApp\WhatsAppProviderResponse;
use App\Enums\Role;
use App\Enums\WalletTransactionType;
use App\Enums\WhatsAppApprovalStatus;
use App\Enums\WhatsAppConnectorStatus;
use App\Enums\WhatsAppProviderErrorCategory;
use App\Enums\WhatsAppProviderResultStatus;
use App\Jobs\PrepareWhatsAppCampaign;
use App\Models\Client;
use App\Models\Contact;
use App\Models\ContactSuppression;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppClientConnector;
use App\Models\WhatsAppConnector;
use App\Models\WhatsAppContactConsent;
use App\Models\WhatsAppPricingRate;
use App\Models\WhatsAppProvider;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\WhatsAppDispatcher;
use App\Services\WhatsApp\WhatsAppPricingService;
use App\Services\WhatsApp\WhatsAppRateLimiter;
use App\Services\WhatsApp\WhatsAppWalletService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

function prepareWhatsAppPhaseThree(Client $client): array
{
    $user = User::factory()->forClient($client)->create();
    $user->syncRoles([Role::ClientAdmin->value]);
    Wallet::factory()->for($client)->create(['balance' => 500, 'reserved_balance' => 0]);
    $provider = WhatsAppProvider::factory()->create(['is_enabled' => true]);
    $connector = WhatsAppConnector::factory()->create(['provider_id' => $provider->id, 'status' => WhatsAppConnectorStatus::Available]);
    $assignment = WhatsAppClientConnector::factory()->create([
        'client_id' => $client->id,
        'connector_id' => $connector->id,
        'enabled' => true,
        'approval_status' => WhatsAppApprovalStatus::Approved,
    ]);
    $template = WhatsAppTemplate::factory()->create(['connector_id' => $connector->id,
        'category' => 'MARKETING',
        'components' => [['type' => 'BODY', 'text' => 'Hello {{1}}']],
    ]);
    foreach (['marketing', 'utility', 'authentication'] as $category) {
        WhatsAppPricingRate::query()->create([
            'market' => 'GH', 'category' => $category, 'currency' => 'USD', 'meta_rate' => 0.02,
            'provider_fee' => 0, 'direct_cost' => 0, 'fx_rate' => 10, 'markup_percent' => 0,
            'minimum_price' => 0, 'selling_price' => 0.15, 'status' => 'approved', 'effective_from' => today(),
        ]);
    }

    return [$user, $connector, $assignment, $template];
}

it('records tenant-owned WhatsApp consent evidence and revokes it with an audit trail', function (): void {
    $client = Client::factory()->create();
    [$user] = prepareWhatsAppPhaseThree($client);
    $contact = Contact::factory()->forClient($client)->create();

    $response = $this->actingAs($user)->post(route('whatsapp.consents.store'), [
        'contact_id' => $contact->id,
        'purpose' => 'marketing',
        'source' => 'Website form',
        'wording' => 'I agree to receive WhatsApp updates.',
        'granted_at' => now()->subDay()->toDateTimeString(),
    ]);
    $response->assertRedirect();
    $consent = WhatsAppContactConsent::query()->firstOrFail();
    expect($consent->wording)->toBe('I agree to receive WhatsApp updates.');

    $this->actingAs($user)->delete(route('whatsapp.consents.revoke', $consent))->assertRedirect();
    expect($consent->fresh()->revoked_at)->not->toBeNull();
});

it('snapshots eligible consented contacts while explaining ineligible contacts and suppressions', function (): void {
    $client = Client::factory()->create();
    [$user, $connector, , $template] = prepareWhatsAppPhaseThree($client);
    $eligible = Contact::factory()->forClient($client)->create(['phone' => '+233201234567']);
    $noConsent = Contact::factory()->forClient($client)->create(['phone' => '+233201234568']);
    $suppressed = Contact::factory()->forClient($client)->create(['phone' => '+233201234569']);
    foreach ([$eligible, $suppressed] as $contact) {
        WhatsAppContactConsent::query()->create([
            'client_id' => $client->id,
            'contact_id' => $contact->id,
            'purpose' => 'marketing',
            'source' => 'Website form',
            'wording' => 'I agree to receive WhatsApp updates.',
            'granted_at' => Carbon::now()->subDay(),
        ]);
    }
    ContactSuppression::query()->create([
        'client_id' => $client->id,
        'channel' => 'whatsapp',
        'identifier' => $suppressed->phone,
        'reason' => 'manual',
        'source' => 'test',
        'is_active' => true,
    ]);

    $this->actingAs($user)->post(route('whatsapp.campaigns.store'), [
        'name' => 'May offers',
        'connector_id' => $connector->id,
        'template_id' => $template->id,
        'schedule_type' => 'now',
        'timezone' => 'Africa/Accra',
        'variable_mapping' => ['1' => 'first_name'],
    ])->assertRedirect();

    $campaign = WhatsAppCampaign::query()->with('recipients')->firstOrFail();
    expect($campaign->recipients->where('eligibility', 'eligible')->pluck('contact_id')->all())->toBe([$eligible->id])
        ->and($campaign->recipients->where('contact_id', $noConsent->id)->first()->errors_json)->toContain('No active consent record for the template category.')
        ->and($campaign->recipients->where('contact_id', $suppressed->id)->first()->errors_json)->toContain('Phone number is suppressed.')
        ->and((float) $campaign->estimated_cost)->toBe(0.15)
        ->and($campaign->recipients->where('contact_id', $eligible->id)->first()->snapshot_json)->toHaveKey('consent_id');
});

it('blocks a sender or template that is not assigned to the current client', function (): void {
    $client = Client::factory()->create();
    [$user] = prepareWhatsAppPhaseThree($client);
    $otherClient = Client::factory()->create();
    [, $otherConnector, , $otherTemplate] = prepareWhatsAppPhaseThree($otherClient);

    $this->actingAs($user)->post(route('whatsapp.campaigns.store'), [
        'name' => 'Cross tenant attempt',
        'connector_id' => $otherConnector->id,
        'template_id' => $otherTemplate->id,
        'schedule_type' => 'now',
        'timezone' => 'Africa/Accra',
    ])->assertSessionHasErrors(['connector_id']);
});

it('rechecks revoked consent immediately before submission and does not contact the provider', function (): void {
    $client = Client::factory()->create();
    [$user, $connector, , $template] = prepareWhatsAppPhaseThree($client);
    $contact = Contact::factory()->forClient($client)->create(['phone' => '+233201234578']);
    $consent = WhatsAppContactConsent::query()->create([
        'client_id' => $client->id, 'contact_id' => $contact->id, 'purpose' => 'marketing',
        'source' => 'Website form', 'wording' => 'I agree.', 'granted_at' => now()->subDay(),
    ]);
    $this->actingAs($user)->post(route('whatsapp.campaigns.store'), [
        'name' => 'Revocation check', 'connector_id' => $connector->id, 'template_id' => $template->id,
        'schedule_type' => 'now', 'timezone' => 'Africa/Accra', 'variable_mapping' => ['1' => 'first_name'],
    ])->assertRedirect();
    $campaign = WhatsAppCampaign::query()->firstOrFail();
    $recipient = $campaign->recipients()->where('contact_id', $contact->id)->firstOrFail();
    $consent->forceFill(['revoked_at' => now()])->save();
    Http::preventStrayRequests();
    config(['whatsapp.sending_enabled' => true]);

    app(WhatsAppDispatcher::class)->sendBatch($campaign->id, [$recipient->id]);

    expect($recipient->fresh()->status)->toBe('ineligible')->and($recipient->fresh()->last_error)->toContain('consent');
    Http::assertNothingSent();
});

it('keeps ambiguous provider outcomes unknown and does not automatically retry them', function (): void {
    $client = Client::factory()->create();
    [$user, $connector, , $template] = prepareWhatsAppPhaseThree($client);
    $contact = Contact::factory()->forClient($client)->create(['phone' => '+233201234579']);
    WhatsAppContactConsent::query()->create([
        'client_id' => $client->id, 'contact_id' => $contact->id, 'purpose' => 'marketing',
        'source' => 'Website form', 'wording' => 'I agree.', 'granted_at' => now()->subDay(),
    ]);
    $this->actingAs($user)->post(route('whatsapp.campaigns.store'), [
        'name' => 'Unknown outcome', 'connector_id' => $connector->id, 'template_id' => $template->id,
        'schedule_type' => 'now', 'timezone' => 'Africa/Accra', 'variable_mapping' => ['1' => 'first_name'],
    ])->assertRedirect();
    $campaign = WhatsAppCampaign::query()->firstOrFail();
    $recipient = $campaign->recipients()->where('contact_id', $contact->id)->firstOrFail();
    $campaign->forceFill(['control_state' => 'active', 'status' => 'sending'])->save();
    $recipient->forceFill(['status' => 'ready'])->save();
    config(['whatsapp.sending_enabled' => true]);
    app(WhatsAppRateLimiter::class);
    app()->instance(WhatsAppProviderContract::class, new class implements WhatsAppProviderContract
    {
        public function testConnection(WhatsAppProvider $provider): WhatsAppProviderResponse
        {
            return $this->response();
        }

        public function getConnectors(WhatsAppProvider $provider): WhatsAppProviderResponse
        {
            return $this->response();
        }

        public function getTemplate(WhatsAppProvider $provider, string $metaTemplateId): WhatsAppProviderResponse
        {
            return $this->response();
        }

        public function getTemplates(WhatsAppProvider $provider, string $wabaId, int $offset = 0, int $limit = 25, ?int $templateStatus = null): WhatsAppProviderResponse
        {
            return $this->response();
        }

        public function send(WhatsAppProvider $provider, array $payload): WhatsAppProviderResponse
        {
            return $this->response();
        }

        public function sendBatch(WhatsAppProvider $provider, array $payload): WhatsAppProviderResponse
        {
            return $this->response();
        }

        private function response(): WhatsAppProviderResponse
        {
            return new WhatsAppProviderResponse(WhatsAppProviderResultStatus::Unknown, 200, null, WhatsAppProviderErrorCategory::Unknown);
        }
    });

    app(WhatsAppDispatcher::class)->sendBatch($campaign->id, [$recipient->id]);

    expect($recipient->fresh()->status)->toBe('unknown')
        ->and($recipient->fresh()->attempt_count)->toBe(1)
        ->and($recipient->attempts()->count())->toBe(1);
});

it('queues a due scheduled campaign once and ignores a second scheduler pass', function (): void {
    Queue::fake();
    $client = Client::factory()->create();
    [$user, $connector, , $template] = prepareWhatsAppPhaseThree($client);
    $campaign = WhatsAppCampaign::query()->create([
        'client_id' => $client->id, 'connector_id' => $connector->id, 'template_id' => $template->id,
        'name' => 'Scheduled', 'status' => 'scheduled', 'control_state' => 'active',
        'schedule_type' => 'scheduled', 'scheduled_at' => now()->subMinute(), 'timezone' => 'Africa/Accra', 'created_by' => $user->id,
    ]);
    config(['whatsapp.sending_enabled' => true]);

    app(DispatchScheduledWhatsAppCampaigns::class)->handle(app(WhatsAppDispatcher::class));
    app(DispatchScheduledWhatsAppCampaigns::class)->handle(app(WhatsAppDispatcher::class));

    Queue::assertPushed(PrepareWhatsAppCampaign::class, 1);
    expect($campaign->fresh()->status)->toBe('preparing');
});

it('does not allow one tenant to pause another tenant campaign', function (): void {
    $owner = Client::factory()->create();
    [, $connector, , $template] = prepareWhatsAppPhaseThree($owner);
    $campaign = WhatsAppCampaign::query()->create([
        'client_id' => $owner->id, 'connector_id' => $connector->id, 'template_id' => $template->id,
        'name' => 'Private', 'status' => 'sending', 'control_state' => 'active', 'schedule_type' => 'now',
        'timezone' => 'Africa/Accra',
    ]);
    $other = Client::factory()->create();
    [$otherUser] = prepareWhatsAppPhaseThree($other);

    $this->actingAs($otherUser)->post(route('whatsapp.campaigns.pause', $campaign))->assertNotFound();
    expect($campaign->fresh()->control_state)->toBe('active');
});

it('quotes only the currently approved effective market and category price', function (): void {
    $client = Client::factory()->create();
    [, , , $template] = prepareWhatsAppPhaseThree($client);
    $pricing = app(WhatsAppPricingService::class);
    $quote = $pricing->estimate($client, $template, '+233201234567');

    expect($pricing->marketFor('+233201234567'))->toBe('GH')
        ->and($quote['price'])->toBe(0.15)
        ->and($quote['snapshot'])->toHaveKeys(['rate_id', 'meta_rate', 'fx_rate', 'markup_percent', 'selling_price']);
});

it('calculates a GHS selling price from the full cost, markup, and minimum price inputs', function (): void {
    $pricing = app(WhatsAppPricingService::class);

    expect($pricing->calculateSellingPrice(0.02, 0.03, 0.01, 10, 25, 0.5))->toBe(0.5)
        ->and($pricing->calculateSellingPrice(0.02, 0.03, 0.01, 10, 25, 0))->toBe(0.3);
});

it('flags overdue unknown reservations for review while keeping funds reserved', function (): void {
    $client = Client::factory()->create();
    [$user, $connector, , $template] = prepareWhatsAppPhaseThree($client);
    $campaign = WhatsAppCampaign::query()->create([
        'client_id' => $client->id, 'connector_id' => $connector->id, 'template_id' => $template->id,
        'name' => 'Unknown review', 'status' => 'sending', 'schedule_type' => 'now', 'timezone' => 'Africa/Accra',
        'estimated_cost' => 10, 'created_by' => $user->id,
    ]);
    $contact = Contact::factory()->forClient($client)->create();
    $recipient = $campaign->recipients()->create([
        'contact_id' => $contact->id, 'phone' => $contact->phone,
        'eligibility' => 'eligible', 'status' => 'unknown', 'snapshot_json' => [], 'unit_price' => 0.15,
    ]);
    $reservation = app(WhatsAppWalletService::class)->reserve($campaign);
    $reservation->forceFill(['unknown_deadline_at' => now()->subMinute()])->save();
    $wallet = Wallet::query()->where('client_id', $client->id)->firstOrFail();

    expect(app(WhatsAppWalletService::class)->flagOverdueUnknownReservations())->toBe(1)
        ->and($reservation->fresh()->exception_flagged_at)->not->toBeNull()
        ->and($reservation->fresh()->heldAmount())->toBe(10.0)
        ->and($wallet->fresh()->reserved_balance)->toBe('10.0000');
});

it('prevents mutation and deletion of wallet ledger entries', function (): void {
    $wallet = Wallet::factory()->create();
    $transaction = $wallet->transactions()->create([
        'type' => WalletTransactionType::Reservation, 'amount' => 1,
        'balance_after' => $wallet->balance, 'description' => 'Immutable check',
    ]);

    expect(fn () => $transaction->update(['description' => 'Changed']))->toThrow(LogicException::class, 'immutable')
        ->and(fn () => $transaction->delete())->toThrow(LogicException::class, 'cannot be deleted');
});

it('limits the client WhatsApp portal and message export to its own tenant', function (): void {
    $client = Client::factory()->create();
    [$user, $connector, , $template] = prepareWhatsAppPhaseThree($client);
    $otherClient = Client::factory()->create();
    [, $otherConnector, , $otherTemplate] = prepareWhatsAppPhaseThree($otherClient);
    $ownCampaign = WhatsAppCampaign::query()->create([
        'client_id' => $client->id, 'connector_id' => $connector->id, 'template_id' => $template->id,
        'name' => '=HYPERLINK("https://bad.example")', 'status' => 'completed', 'schedule_type' => 'now', 'timezone' => 'Africa/Accra',
    ]);
    $otherCampaign = WhatsAppCampaign::query()->create([
        'client_id' => $otherClient->id, 'connector_id' => $otherConnector->id, 'template_id' => $otherTemplate->id,
        'name' => 'Other tenant campaign', 'status' => 'completed', 'schedule_type' => 'now', 'timezone' => 'Africa/Accra',
    ]);
    $ownContact = Contact::factory()->forClient($client)->create(['phone' => '+233201234501']);
    $otherContact = Contact::factory()->forClient($otherClient)->create(['phone' => '+233201234502']);
    $ownRecipient = $ownCampaign->recipients()->create([
        'contact_id' => $ownContact->id, 'phone' => $ownContact->phone, 'eligibility' => 'eligible', 'status' => 'delivered', 'snapshot_json' => [],
    ]);
    $otherCampaign->recipients()->create([
        'contact_id' => $otherContact->id, 'phone' => $otherContact->phone, 'eligibility' => 'eligible', 'status' => 'delivered', 'snapshot_json' => [],
    ]);

    $this->actingAs($user)->get(route('whatsapp.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('whatsapp/index')
        ->has('messages', 1)
        ->where('messages.0.id', $ownRecipient->id));
    $export = $this->actingAs($user)->get(route('whatsapp.messages.export'))->assertOk()->assertDownload();
    $csv = $export->streamedContent();

    expect($csv)->toContain("'=HYPERLINK")
        ->and($csv)->toContain('+233201234501')
        ->and($csv)->not->toContain('+233201234502')
        ->and($csv)->not->toContain('Other tenant campaign');
});

it('reserves WhatsApp funds atomically in the shared wallet without touching package units', function (): void {
    $client = Client::factory()->create();
    [$user, $connector, , $template] = prepareWhatsAppPhaseThree($client);
    $wallet = Wallet::query()->where('client_id', $client->id)->firstOrFail();
    $campaign = WhatsAppCampaign::query()->create([
        'client_id' => $client->id, 'connector_id' => $connector->id, 'template_id' => $template->id,
        'name' => 'Reserve', 'status' => 'draft', 'schedule_type' => 'now', 'timezone' => 'Africa/Accra',
        'estimated_cost' => 15.25, 'price_snapshot' => ['currency' => 'GHS'], 'created_by' => $user->id,
    ]);
    $reservation = app(WhatsAppWalletService::class)->reserve($campaign);

    expect($wallet->fresh()->balance)->toBe('500.0000')
        ->and($wallet->fresh()->reserved_balance)->toBe('15.2500')
        ->and($reservation->heldAmount())->toBe(15.25)
        ->and($campaign->fresh()->wallet_reservation_id)->toBe($reservation->id)
        ->and($wallet->transactions()->where('type', 'reservation')->whereJsonContains('metadata->channel', 'whatsapp')->exists())->toBeTrue();
});

it('refuses to settle a WhatsApp reservation without verified billable delivery evidence', function (): void {
    $client = Client::factory()->create();
    [$user, $connector, , $template] = prepareWhatsAppPhaseThree($client);
    $campaign = WhatsAppCampaign::query()->create([
        'client_id' => $client->id, 'connector_id' => $connector->id, 'template_id' => $template->id,
        'name' => 'Evidence gate', 'status' => 'draft', 'schedule_type' => 'now', 'timezone' => 'Africa/Accra',
        'estimated_cost' => 10, 'created_by' => $user->id,
    ]);
    app(WhatsAppWalletService::class)->reserve($campaign);

    expect(fn () => app(WhatsAppWalletService::class)->settleDelivered($campaign, 'settlement-test', 0.15, []))
        ->toThrow(LogicException::class, 'verified billable delivery evidence');
});
