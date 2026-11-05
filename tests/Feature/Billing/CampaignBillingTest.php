<?php

use App\Enums\BillingUnit;
use App\Enums\CampaignContactStatus;
use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Enums\ReservationStatus;
use App\Enums\WalletTransactionType;
use App\Models\BillingReservation;
use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\Client;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\Rate;
use App\Models\RateCard;
use App\Models\SenderId;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\CampaignDispatcher;
use App\Services\CampaignService;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    config()->set('campaigns.require_approval', false);
    config()->set('campaigns.require_approved_audio', false);
    config()->set('email.driver', 'log');
    config()->set('email.broadcast.enabled', true);
    config()->set('services.voice.outbound_caller_id', '+233200000000');

    [$this->client, $this->wallet, $this->user] = clientWithWallet(balance: 20.0, smsPrice: 0.05);

    $this->service = app(CampaignService::class);

    $list = ContactList::factory()->forClient($this->client)->create();
    $this->contacts = Contact::factory()->count(2)->forClient($this->client)->create();
    $list->contacts()->attach($this->contacts->pluck('id'), ['added_at' => now()]);

    $sender = SenderId::factory()->for($this->client)->sms()->approved()->create(['name' => 'OmniCast']);

    $this->draft = fn (array $attributes = []): Campaign => $this->service->create([
        'client_id' => $this->client->id,
        'name' => 'Balance reminder',
        'channel' => Channel::Sms,
        'contact_list_id' => $list->id,
        'sender_id' => $sender->id,
        'sms_message' => 'Your balance is ready.',
        ...$attributes,
    ], $this->user);

    $this->running = function (array $attributes = []): Campaign {
        $campaign = ($this->draft)($attributes);

        $this->service->submit($campaign, $this->user);
        $this->service->launch($campaign->refresh());

        return $campaign->refresh();
    };
});

it('debits the wallet for every SMS part as it is delivered', function (): void {
    $campaign = ($this->running)(['sms_message' => str_repeat('a', 200)]);

    expect(round((float) $this->wallet->refresh()->reserved_balance, 4))->toBe(0.2)
        ->and(round((float) $this->wallet->balance, 4))->toBe(20.0);

    app(CampaignDispatcher::class)->run($campaign);

    $debits = WalletTransaction::query()
        ->where('campaign_id', $campaign->id)
        ->where('type', WalletTransactionType::Debit->value)
        ->orderBy('id')
        ->get();

    expect($debits)->toHaveCount(2)
        ->and($debits->map(fn (WalletTransaction $debit): float => round((float) $debit->amount, 4))->all())->toBe([0.1, 0.1])
        ->and($debits->map(fn (WalletTransaction $debit): float => round((float) $debit->balance_after, 4))->all())->toBe([19.9, 19.8])
        ->and($debits->first()->metadata['channel'])->toBe('sms')
        ->and(round((float) $this->wallet->refresh()->balance, 4))->toBe(19.8)
        ->and(round((float) $this->wallet->reserved_balance, 4))->toBe(0.0);

    $reservation = BillingReservation::query()->where('campaign_id', $campaign->id)->sole();

    expect($reservation->status)->toBe(ReservationStatus::Captured)
        ->and(round((float) $reservation->captured_amount, 4))->toBe(0.2)
        ->and(round((float) $reservation->released_amount, 4))->toBe(0.0);

    $campaign->refresh();

    expect($campaign->status)->toBe(CampaignStatus::Completed)
        ->and(round((float) $campaign->actual_cost, 4))->toBe(0.2)
        ->and(round((float) $campaign->reserved_amount, 4))->toBe(0.0);
});

it('bills a service at the platform default rate when the client has no card', function (): void {
    $card = RateCard::factory()->platformDefault()->create(['channel' => Channel::Email]);
    Rate::factory()->for($card, 'rateCard')->create([
        'channel' => Channel::Email,
        'billing_unit' => BillingUnit::PerEmail,
        'unit_price' => 0.05,
    ]);

    $client = Client::factory()->create();
    $wallet = Wallet::factory()->for($client)->create(['balance' => 5.0]);
    $user = User::factory()->forClient($client)->create();

    $list = ContactList::factory()->forClient($client)->create();
    $contacts = Contact::factory()->count(2)->forClient($client)->create();
    $list->contacts()->attach($contacts->pluck('id'), ['added_at' => now()]);

    $sender = SenderId::factory()->for($client)->email()->approved()->create(['name' => 'billing@acme.test']);

    $campaign = $this->service->create([
        'client_id' => $client->id,
        'name' => 'Statement broadcast',
        'channel' => Channel::Email,
        'contact_list_id' => $list->id,
        'sender_id' => $sender->id,
        'email_subject' => 'Your statement',
        'email_body' => '<p>Your statement is ready.</p>',
    ], $user);

    expect(round((float) $campaign->estimated_cost, 4))->toBe(0.1);

    $this->service->submit($campaign, $user);
    $this->service->launch($campaign->refresh());

    app(CampaignDispatcher::class)->run($campaign->refresh());

    expect(round((float) $wallet->refresh()->balance, 4))->toBe(4.9)
        ->and(round((float) $wallet->reserved_balance, 4))->toBe(0.0)
        ->and(round((float) $campaign->refresh()->actual_cost, 4))->toBe(0.1);
});

it('releases the part of the hold the campaign did not spend', function (): void {
    $campaign = ($this->running)();

    $this->contacts->first()->forceFill(['dnd' => true, 'dnd_at' => now()])->save();

    app(CampaignDispatcher::class)->run($campaign);

    $reservation = BillingReservation::query()->where('campaign_id', $campaign->id)->sole();

    expect(round((float) $this->wallet->refresh()->balance, 4))->toBe(19.95)
        ->and(round((float) $this->wallet->reserved_balance, 4))->toBe(0.0)
        ->and(round((float) $reservation->captured_amount, 4))->toBe(0.05)
        ->and(round((float) $reservation->released_amount, 4))->toBe(0.05)
        ->and($reservation->status)->toBe(ReservationStatus::Captured)
        ->and(round((float) $campaign->refresh()->actual_cost, 4))->toBe(0.05);
});

it('stops a campaign when the wallet cannot pay for the next unit', function (): void {
    $campaign = ($this->running)();

    $this->wallet->forceFill(['balance' => 0.12])->save();

    // The message grew after it was priced, so it now costs more than the hold.
    $campaign->forceFill(['sms_message' => str_repeat('a', 400)])->save();

    app(CampaignDispatcher::class)->run($campaign->refresh());

    $contacts = CampaignContact::query()->where('campaign_id', $campaign->id)->get();

    expect(round((float) $this->wallet->refresh()->balance, 4))->toBe(0.12)
        ->and(round((float) $this->wallet->reserved_balance, 4))->toBe(0.0)
        ->and($contacts->pluck('status')->unique()->all())->toBe([CampaignContactStatus::Failed])
        ->and($contacts->first()->error_message)->toContain('wallet balance cannot cover')
        ->and(round((float) $campaign->refresh()->actual_cost, 4))->toBe(0.0)
        ->and(WalletTransaction::query()->where('campaign_id', $campaign->id)->where('type', WalletTransactionType::Debit->value)->count())->toBe(0);
});

it('bills every leg of a multi-channel campaign', function (): void {
    $voiceCard = RateCard::factory()->for($this->client)->create(['channel' => Channel::Voice]);
    Rate::factory()->for($voiceCard, 'rateCard')->create([
        'channel' => Channel::Voice,
        'billing_unit' => BillingUnit::PerMinute,
        'unit_price' => 0.3,
    ]);

    $campaign = ($this->running)([
        'channel' => Channel::MultiChannel,
        'voice_script' => 'Your balance is ready.',
    ]);

    expect(round((float) $campaign->estimated_cost, 4))->toBe(0.7);

    app(CampaignDispatcher::class)->run($campaign);

    $debits = WalletTransaction::query()
        ->where('campaign_id', $campaign->id)
        ->where('type', WalletTransactionType::Debit->value)
        ->get();

    expect($debits)->toHaveCount(4)
        ->and(round($debits->sum(fn (WalletTransaction $debit): float => (float) $debit->amount), 4))->toBe(0.7)
        ->and($debits->pluck('metadata')->pluck('channel')->unique()->sort()->values()->all())->toBe(['sms', 'voice'])
        ->and(round((float) $this->wallet->refresh()->balance, 4))->toBe(19.3)
        ->and(round((float) $this->wallet->reserved_balance, 4))->toBe(0.0)
        ->and(round((float) $campaign->refresh()->actual_cost, 4))->toBe(0.7);
});

it('holds the estimate at launch for a campaign approved before it was priced', function (): void {
    $campaign = ($this->draft)();

    $campaign->forceFill(['status' => CampaignStatus::Approved, 'approved_at' => now()])->save();

    expect(BillingReservation::query()->where('campaign_id', $campaign->id)->count())->toBe(0);

    $this->service->launch($campaign->refresh());

    $reservation = BillingReservation::query()->where('campaign_id', $campaign->id)->sole();

    expect($reservation->status)->toBe(ReservationStatus::Held)
        ->and(round((float) $reservation->amount, 4))->toBe(0.1)
        ->and(round((float) $this->wallet->refresh()->reserved_balance, 4))->toBe(0.1);
});
