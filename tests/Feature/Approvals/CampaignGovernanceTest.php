<?php

use App\Contracts\VoiceGateway;
use App\Enums\CampaignAudioStatus;
use App\Enums\CampaignContactStatus;
use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Enums\Role as RoleEnum;
use App\Enums\SenderIdStatus;
use App\Jobs\DispatchCampaignJob;
use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\SenderId;
use App\Services\CampaignDispatcher;
use App\Services\CampaignReadiness;
use App\Services\CampaignService;
use App\Services\Voice\VoiceCall;
use App\Services\Voice\VoiceDispatchResult;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Queue::fake();
    config()->set('campaigns.require_approval', true);
    config()->set('services.voice.outbound_caller_id', '+233200000000');

    [$this->client, $this->wallet, $this->user] = clientWithWallet(balance: 20.0, smsPrice: 0.05);

    $this->service = app(CampaignService::class);
    $this->readiness = app(CampaignReadiness::class);
    $this->reviewer = platformUser(RoleEnum::ComplianceOfficer);

    $this->sender = SenderId::factory()->for($this->client)->sms()->approved()->create();

    $this->list = ContactList::factory()->forClient($this->client)->create();
    $this->contacts = Contact::factory()->count(2)->forClient($this->client)->create();

    $this->list->contacts()->attach($this->contacts->pluck('id'), ['added_at' => now()]);

    $this->draft = fn (array $attributes = []): Campaign => $this->service->create([
        'client_id' => $this->client->id,
        'name' => 'Governed campaign',
        'channel' => Channel::Sms,
        'contact_list_id' => $this->list->id,
        'sender_id' => $this->sender->id,
        'sms_message' => 'Your statement is ready.',
        ...$attributes,
    ], $this->user);

    $this->voiceDraft = fn (array $attributes = []): Campaign => ($this->draft)([
        'name' => 'Voice reminder',
        'channel' => Channel::Voice,
        'sender_id' => null,
        'sms_message' => null,
        'voice_script' => 'Hello, your statement is ready.',
        ...$attributes,
    ]);
});

it('refuses to submit an sms campaign without an approved sender id', function (): void {
    $campaign = ($this->draft)(['sender_id' => null]);

    expect(fn () => $this->service->submit($campaign, $this->user))
        ->toThrow(ValidationException::class);

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Draft);
});

it('refuses to submit while the sender id is still pending review', function (): void {
    $pending = SenderId::factory()->for($this->client)->sms()->create();
    $campaign = ($this->draft)(['sender_id' => $pending->id]);

    expect(fn () => $this->service->submit($campaign, $this->user))
        ->toThrow(ValidationException::class);

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Draft);
});

it('only accepts a sender id issued for the channel the campaign broadcasts on', function (): void {
    $email = SenderId::factory()->for($this->client)->email()->approved()->create();
    $campaign = ($this->draft)(['sender_id' => $email->id]);

    $issues = $this->readiness->issues($campaign);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]['key'])->toBe('sender_channel')
        ->and(fn () => $this->service->submit($campaign, $this->user))
        ->toThrow(ValidationException::class);
});

it('submits, approves and launches an sms campaign under an approved sender id', function (): void {
    $campaign = ($this->draft)();

    $this->service->submit($campaign, $this->user);
    $this->service->approve($campaign->refresh(), $this->reviewer);
    $this->service->launch($campaign->refresh());

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Running);
});

it('blocks campaign approval until the voice audio is approved', function (): void {
    $campaign = ($this->voiceDraft)();

    $this->service->submit($campaign, $this->user);

    expect($campaign->refresh()->audio_status)->toBe(CampaignAudioStatus::Pending)
        ->and(fn () => $this->service->approve($campaign->refresh(), $this->reviewer))
        ->toThrow(ValidationException::class);

    $this->service->approveAudio($campaign->refresh(), $this->reviewer);
    $campaign->refresh();

    expect($campaign->audio_status)->toBe(CampaignAudioStatus::Approved)
        ->and($campaign->audio_reviewed_by)->toBe($this->reviewer->id);

    $this->service->approve($campaign->refresh(), $this->reviewer);
    $this->service->launch($campaign->refresh());

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Running);
});
it('keeps a campaign blocked when a reviewer rejects the audio', function (): void {
    $campaign = ($this->voiceDraft)();
    $this->service->submit($campaign, $this->user);

    $this->service->rejectAudio($campaign->refresh(), $this->reviewer, 'The script promises a guaranteed return.');

    $campaign->refresh();
    $keys = array_map(fn (array $issue): string => $issue['key'], $this->readiness->issues($campaign));

    expect($campaign->audio_status)->toBe(CampaignAudioStatus::Rejected)
        ->and($campaign->audio_rejection_reason)->toBe('The script promises a guaranteed return.')
        ->and($keys)->toContain('audio')
        ->and(fn () => $this->service->approve($campaign->refresh(), $this->reviewer))
        ->toThrow(ValidationException::class);
});

it('refuses audio review for campaigns without voice content', function (): void {
    $campaign = ($this->draft)();
    $this->service->submit($campaign, $this->user);

    expect(fn () => $this->service->approveAudio($campaign->refresh(), $this->reviewer))
        ->toThrow(ValidationException::class);
});

it('blocks voice campaigns until a platform did is configured', function (): void {
    $campaign = ($this->voiceDraft)();
    $this->service->submit($campaign, $this->user);
    $this->service->approveAudio($campaign->refresh(), $this->reviewer);

    config()->set('services.voice.outbound_caller_id', null);

    $keys = array_map(fn (array $issue): string => $issue['key'], $this->readiness->issues($campaign->refresh()));

    expect($keys)->toContain('voice_did')
        ->and(fn () => $this->service->approve($campaign->refresh(), $this->reviewer))
        ->toThrow(ValidationException::class);
});

it('dials every voice campaign from the platform did', function (): void {
    $gateway = new class implements VoiceGateway
    {
        /** @var list<array{to: string, from: string, reference: string}> */
        public array $calls = [];

        public function dial(VoiceCall $call): VoiceDispatchResult
        {
            $this->calls[] = [
                'to' => $call->to,
                'from' => $call->from,
                'reference' => $call->reference,
            ];

            return VoiceDispatchResult::accepted('switch-'.$call->reference);
        }

        public function name(): string
        {
            return 'fake';
        }
    };

    $this->app->instance(VoiceGateway::class, $gateway);

    $campaign = ($this->voiceDraft)();
    $this->service->submit($campaign, $this->user);
    $this->service->approveAudio($campaign->refresh(), $this->reviewer);
    $this->service->approve($campaign->refresh(), $this->reviewer);
    $this->service->launch($campaign->refresh());

    app(CampaignDispatcher::class)->run($campaign->refresh());

    expect($gateway->calls)->toHaveCount(2)
        ->and(array_unique(array_column($gateway->calls, 'from')))->toBe(['+233200000000'])
        ->and($campaign->refresh()->status)->toBe(CampaignStatus::Completed)
        ->and($campaign->voiceCalls()->count())->toBe(2)
        ->and($campaign->voiceCalls()->pluck('provider')->unique()->all())->toBe(['fake']);
});

it('fails the recipients when the approved sender id is suspended before dispatch', function (): void {
    $campaign = ($this->draft)();
    $this->service->submit($campaign, $this->user);
    $this->service->approve($campaign->refresh(), $this->reviewer);
    $this->service->launch($campaign->refresh());

    $this->sender->update(['status' => SenderIdStatus::Suspended]);

    $stillRetrying = app(CampaignDispatcher::class)->run($campaign->refresh());

    $failed = CampaignContact::query()
        ->where('campaign_id', $campaign->id)
        ->where('status', CampaignContactStatus::Failed->value)
        ->get();

    expect($stillRetrying)->toBeFalse()
        ->and($campaign->refresh()->status)->toBe(CampaignStatus::Completed)
        ->and($failed)->toHaveCount(2)
        ->and($failed->first()->error_message)->toContain('Sender ID');
});

it('shows the review queues to a compliance reviewer', function (): void {
    $campaign = ($this->draft)();
    $this->service->submit($campaign, $this->user);

    $voice = ($this->voiceDraft)();
    $this->service->submit($voice, $this->user);

    SenderId::factory()->for($this->client)->voice()->create();

    $this->actingAs($this->reviewer)
        ->get(route('approvals.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('approvals/index')
            ->where('queues.campaigns.total', 2)
            ->where('queues.audio.total', 1)
            ->where('queues.sender_ids.total', 1)
            ->where('abilities.campaigns', true)
            ->where('abilities.sender_ids', true)
            ->where('outboundCallerId', '+233200000000'));
});

it('forbids tenant users from opening the approvals workspace', function (): void {
    $this->actingAs($this->user)
        ->get(route('approvals.index'))
        ->assertForbidden();
});

it('approves and rejects campaigns from the review queue', function (): void {
    $campaign = ($this->draft)();
    $this->service->submit($campaign, $this->user);

    $this->actingAs($this->reviewer)
        ->post(route('campaigns.approve', $campaign))
        ->assertSessionHasNoErrors();

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Approved);

    $second = ($this->draft)();
    $this->service->submit($second, $this->user);

    $this->actingAs($this->reviewer)
        ->post(route('campaigns.reject', $second), ['reason' => 'Copy needs an opt-out instruction.'])
        ->assertSessionHasNoErrors();

    expect($second->refresh()->status)->toBe(CampaignStatus::Rejected)
        ->and($second->rejection_reason)->toBe('Copy needs an opt-out instruction.');
});

it('requires a reason before audio can be rejected', function (): void {
    $campaign = ($this->voiceDraft)();
    $this->service->submit($campaign, $this->user);

    $this->actingAs($this->reviewer)
        ->post(route('campaigns.audio.reject', $campaign), ['reason' => 'no'])
        ->assertSessionHasErrors('reason');

    expect($campaign->refresh()->audio_status)->toBe(CampaignAudioStatus::Pending);

    $this->actingAs($this->reviewer)
        ->post(route('campaigns.audio.reject', $campaign), ['reason' => 'The script names a competitor.'])
        ->assertSessionHasNoErrors();

    expect($campaign->refresh()->audio_status)->toBe(CampaignAudioStatus::Rejected)
        ->and($campaign->audio_rejection_reason)->toBe('The script names a competitor.');
});

it('lets the review desk clear voice audio through the endpoint', function (): void {
    $campaign = ($this->voiceDraft)();
    $this->service->submit($campaign, $this->user);

    $this->actingAs($this->reviewer)
        ->post(route('campaigns.audio.approve', $campaign))
        ->assertSessionHasNoErrors();

    expect($campaign->refresh()->audio_status)->toBe(CampaignAudioStatus::Approved)
        ->and($campaign->audio_reviewed_by)->toBe($this->reviewer->id);
});

it('forbids a tenant user from clearing voice audio', function (): void {
    $campaign = ($this->voiceDraft)();
    $this->service->submit($campaign, $this->user);

    $this->actingAs($this->user)
        ->post(route('campaigns.audio.approve', $campaign))
        ->assertForbidden();

    expect($campaign->refresh()->audio_status)->toBe(CampaignAudioStatus::Pending);
});

it('approves an sms campaign as soon as it is submitted under an approved sender id', function (): void {
    config()->set('campaigns.require_approval', false);

    $campaign = ($this->draft)();

    $submitted = $this->service->submit($campaign, $this->user);

    expect($submitted->status)->toBe(CampaignStatus::Approved)
        ->and($submitted->submitted_at)->not->toBeNull()
        ->and($submitted->approved_at)->not->toBeNull()
        ->and($submitted->approved_by)->toBeNull();

    $this->service->launch($submitted->refresh());

    expect($submitted->refresh()->status)->toBe(CampaignStatus::Running);

    Queue::assertPushed(DispatchCampaignJob::class);
});

it('approves a voice campaign the moment its audio clears', function (): void {
    config()->set('campaigns.require_approval', false);

    $campaign = ($this->voiceDraft)();

    $this->service->submit($campaign, $this->user);

    expect($campaign->refresh()->status)->toBe(CampaignStatus::PendingApproval)
        ->and($campaign->approved_at)->toBeNull();

    $this->service->approveAudio($campaign->refresh(), $this->reviewer);

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Approved)
        ->and($campaign->approved_at)->not->toBeNull()
        ->and($campaign->approved_by)->toBeNull()
        ->and($campaign->audio_reviewed_by)->toBe($this->reviewer->id);

    $this->service->launch($campaign->refresh());

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Running);

    Queue::assertPushed(DispatchCampaignJob::class);
});

it('keeps a campaign in review while the platform still wants a human decision', function (): void {
    $campaign = ($this->draft)();

    $this->service->submit($campaign, $this->user);

    expect($campaign->refresh()->status)->toBe(CampaignStatus::PendingApproval)
        ->and($campaign->approved_at)->toBeNull();

    $this->service->approve($campaign->refresh(), $this->reviewer);

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Approved)
        ->and($campaign->approved_by)->toBe($this->reviewer->id);
});

it('still refuses an unapproved sender id when campaigns may self approve', function (): void {
    config()->set('campaigns.require_approval', false);

    $pending = SenderId::factory()->for($this->client)->sms()->create();
    $campaign = ($this->draft)(['sender_id' => $pending->id]);

    expect(fn () => $this->service->submit($campaign, $this->user))
        ->toThrow(ValidationException::class);

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Draft)
        ->and($campaign->approved_at)->toBeNull();
});
