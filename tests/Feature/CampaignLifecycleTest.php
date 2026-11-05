<?php

use App\Contracts\SmsGateway;
use App\Enums\CampaignContactStatus;
use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Enums\ReservationStatus;
use App\Jobs\DispatchCampaignJob;
use App\Models\BillingReservation;
use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\ContactSegment;
use App\Models\ContactSuppression;
use App\Models\SenderId;
use App\Services\CampaignDispatcher;
use App\Services\CampaignService;
use App\Services\Delivery\QueueTopology;
use App\Services\Sms\OutboundSms;
use App\Services\Sms\SmsDispatchResult;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    [$this->client, $this->wallet, $this->user] = clientWithWallet(balance: 10.0, smsPrice: 0.05);

    $this->service = app(CampaignService::class);

    $this->list = ContactList::factory()->forClient($this->client)->create();
    $this->contacts = Contact::factory()->count(4)->forClient($this->client)->create();

    $this->list->contacts()->attach($this->contacts->pluck('id'), ['added_at' => now()]);

    $this->sender = SenderId::factory()->for($this->client)->sms()->approved()->create();

    $this->draft = fn (array $attributes = []): Campaign => $this->service->create([
        'client_id' => $this->client->id,
        'name' => 'Balance reminder',
        'channel' => Channel::Sms,
        'contact_list_id' => $this->list->id,
        'sender_id' => $this->sender->id,
        'sms_message' => str_repeat('a', 200),
        ...$attributes,
    ], $this->user);
});

it('prices a draft from the audience size and the sms segmentation', function (): void {
    $campaign = ($this->draft)();

    expect($campaign->status)->toBe(CampaignStatus::Draft)
        ->and($campaign->reference)->toStartWith('CMP-')
        ->and($campaign->estimated_recipients)->toBe(4)
        ->and($campaign->estimated_sms_parts)->toBe(2)
        ->and((float) $campaign->estimated_cost)->toBe(0.4);
});

it('holds the estimated spend when a campaign is submitted', function (): void {
    $campaign = ($this->draft)();

    $this->service->submit($campaign, $this->user);

    $reservation = BillingReservation::query()->where('campaign_id', $campaign->id)->sole();

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Approved)
        ->and($campaign->submitted_at)->not->toBeNull()
        ->and($campaign->approved_at)->not->toBeNull()
        ->and($campaign->approved_by)->toBeNull()
        ->and(CampaignContact::query()->where('campaign_id', $campaign->id)->count())->toBe(4)
        ->and($reservation->status)->toBe(ReservationStatus::Held)
        ->and((float) $reservation->amount)->toBe(0.4)
        ->and((float) $this->wallet->refresh()->reserved_balance)->toBe(0.4)
        ->and((float) $this->wallet->balance)->toBe(10.0);
});

it('leaves a campaign in the review queue when the platform still requires approval', function (): void {
    config()->set('campaigns.require_approval', true);

    $campaign = ($this->draft)();

    $this->service->submit($campaign, $this->user);

    expect($campaign->refresh()->status)->toBe(CampaignStatus::PendingApproval)
        ->and($campaign->approved_at)->toBeNull()
        ->and(fn () => $this->service->launch($campaign->refresh()))
        ->toThrow(ValidationException::class);
});

it('refuses to submit when the wallet cannot cover the estimate', function (): void {
    $this->wallet->update(['balance' => 0.01]);

    $campaign = ($this->draft)();

    expect(fn () => $this->service->submit($campaign, $this->user))
        ->toThrow(ValidationException::class, 'The wallet balance cannot cover the estimated GHS 0.40.');

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Draft)
        ->and(BillingReservation::query()->count())->toBe(0)
        ->and((float) $this->wallet->refresh()->reserved_balance)->toBe(0.0);
});

it('releases the held funds when a campaign is rejected', function (): void {
    $campaign = ($this->draft)();
    $this->service->submit($campaign, $this->user);

    $this->service->reject($campaign, $this->user, 'The message is missing an opt out instruction.');

    $reservation = BillingReservation::query()->where('campaign_id', $campaign->id)->sole();

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Rejected)
        ->and($campaign->rejection_reason)->toBe('The message is missing an opt out instruction.')
        ->and($reservation->status)->toBe(ReservationStatus::Released)
        ->and((float) $reservation->released_amount)->toBe(0.4)
        ->and((float) $this->wallet->refresh()->reserved_balance)->toBe(0.0)
        ->and((float) $this->wallet->balance)->toBe(10.0);
});

it('queues the campaign for dispatch as soon as it is launched', function (): void {
    Queue::fake();

    $campaign = ($this->draft)();
    $this->service->submit($campaign, $this->user);
    $this->service->launch($campaign);

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Running)
        ->and($campaign->launched_at)->not->toBeNull();

    Queue::assertPushed(DispatchCampaignJob::class, fn (DispatchCampaignJob $job): bool => $job->campaignId === $campaign->id);
});

it('waits for the start time when a campaign is scheduled', function (): void {
    Queue::fake();

    $campaign = ($this->draft)([
        'schedule_type' => 'scheduled',
        'scheduled_at' => now()->addHours(2),
    ]);

    $this->service->submit($campaign, $this->user);
    $this->service->launch($campaign);

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Scheduled)
        ->and($campaign->launched_at)->toBeNull();

    Queue::assertPushed(DispatchCampaignJob::class);
});

it('delivers to every recipient, captures the real spend and releases the rest', function (): void {
    Queue::fake();

    $campaign = ($this->draft)();
    $this->service->submit($campaign, $this->user);
    $this->service->launch($campaign);

    $stillRetrying = app(CampaignDispatcher::class)->run($campaign->refresh());

    $reservation = BillingReservation::query()->where('campaign_id', $campaign->id)->sole();

    expect($stillRetrying)->toBeFalse()
        ->and($campaign->refresh()->status)->toBe(CampaignStatus::Completed)
        ->and($campaign->completed_at)->not->toBeNull()
        ->and((float) $campaign->actual_cost)->toBe(0.4)
        ->and(CampaignContact::query()->where('campaign_id', $campaign->id)->where('status', CampaignContactStatus::Delivered->value)->count())->toBe(4)
        ->and((float) $reservation->refresh()->captured_amount)->toBe(0.4)
        ->and((float) $reservation->released_amount)->toBe(0.0)
        ->and($reservation->status)->toBe(ReservationStatus::Captured)
        ->and((float) $this->wallet->refresh()->balance)->toBe(9.6)
        ->and((float) $this->wallet->reserved_balance)->toBe(0.0);
});

it('skips a recipient that became unreachable after the campaign was submitted', function (): void {
    Queue::fake();

    $campaign = ($this->draft)();
    $this->service->submit($campaign, $this->user);
    $this->service->launch($campaign);

    $blocked = $this->contacts->first();
    $blocked->update(['dnd' => true]);

    app(CampaignDispatcher::class)->run($campaign->refresh());

    $skipped = CampaignContact::query()
        ->where('campaign_id', $campaign->id)
        ->where('contact_id', $blocked->id)
        ->sole();

    expect($skipped->status)->toBe(CampaignContactStatus::Skipped)
        ->and((float) $skipped->cost)->toBe(0.0)
        ->and($campaign->refresh()->status)->toBe(CampaignStatus::Completed)
        ->and((float) $campaign->actual_cost)->toBe(0.3)
        ->and((float) $this->wallet->refresh()->balance)->toBe(9.7);
});

it('records suppression evidence in the audience snapshot and rechecks the DND registry before sending', function (): void {
    Queue::fake();
    $campaign = ($this->draft)();
    $this->service->submit($campaign, $this->user);
    $this->service->launch($campaign);

    $contact = $this->contacts->first();
    ContactSuppression::query()->create([
        'client_id' => null,
        'channel' => 'sms',
        'identifier' => $contact->phone,
        'reason' => 'dnd',
        'source' => 'test',
        'is_active' => true,
    ]);

    app(CampaignDispatcher::class)->run($campaign->refresh());
    $recipient = CampaignContact::query()->where('campaign_id', $campaign->id)->where('contact_id', $contact->id)->sole();

    expect($recipient->status)->toBe(CampaignContactStatus::Skipped)
        ->and($recipient->recipient_snapshot['sms']['eligible'] ?? $recipient->recipient_snapshot['channels']['sms']['eligible'])->toBeFalse()
        ->and($recipient->recipient_snapshot['channels']['sms']['suppression_id'])->not->toBeNull();
});

it('uses a tenant saved segment as the campaign audience', function (): void {
    $this->contacts[0]->update(['company' => 'Acme Ghana']);
    $this->contacts[1]->update(['company' => 'Acme Retail']);
    $this->contacts[2]->update(['company' => 'Other']);
    $this->contacts[3]->update(['company' => 'Other']);
    $segment = ContactSegment::factory()->for($this->client)->create([
        'name' => 'Acme accounts',
        'filters' => ['company' => 'Acme'],
    ]);

    $campaign = ($this->draft)(['contact_list_id' => null, 'contact_segment_id' => $segment->id]);

    expect($campaign->estimated_recipients)->toBe(2);
});

it('retries failed recipients until the retry budget is spent', function (): void {
    Queue::fake();

    $this->app->bind(SmsGateway::class, fn (): SmsGateway => new class implements SmsGateway
    {
        public function send(OutboundSms $message): SmsDispatchResult
        {
            return SmsDispatchResult::rejected('The gateway rejected the message.');
        }

        public function name(): string
        {
            return 'fake';
        }
    });

    $campaign = ($this->draft)();
    $this->service->submit($campaign, $this->user);
    $this->service->launch($campaign);

    $dispatcher = app(CampaignDispatcher::class);

    expect($dispatcher->run($campaign->refresh()))->toBeTrue()
        ->and(CampaignContact::query()->where('campaign_id', $campaign->id)->where('status', CampaignContactStatus::Queued->value)->count())->toBe(4);

    $dispatcher->run($campaign->refresh());

    expect($dispatcher->run($campaign->refresh()))->toBeFalse()
        ->and($campaign->refresh()->status)->toBe(CampaignStatus::Completed)
        ->and((float) $campaign->actual_cost)->toBe(0.0)
        ->and(CampaignContact::query()->where('campaign_id', $campaign->id)->where('status', CampaignContactStatus::Failed->value)->count())->toBe(4)
        ->and((float) $this->wallet->refresh()->balance)->toBe(10.0)
        ->and((float) $this->wallet->reserved_balance)->toBe(0.0);
});

it('finishes the campaign through the dispatch job without requeueing it', function (): void {
    Queue::fake();

    $campaign = ($this->draft)();
    $this->service->submit($campaign, $this->user);
    $this->service->launch($campaign);

    (new DispatchCampaignJob($campaign->id))->handle(app(CampaignDispatcher::class), app(QueueTopology::class));

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Completed);

    Queue::assertPushed(DispatchCampaignJob::class, 1);
});

it('leaves a campaign that is not ready for dispatch untouched', function (): void {
    Queue::fake();

    $campaign = ($this->draft)();
    $campaign->forceFill(['status' => CampaignStatus::Paused])->save();

    (new DispatchCampaignJob($campaign->id))->handle(app(CampaignDispatcher::class), app(QueueTopology::class));

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Paused)
        ->and(CampaignContact::query()->where('campaign_id', $campaign->id)->count())->toBe(0);

    Queue::assertNotPushed(DispatchCampaignJob::class);
});
