<?php

use App\Enums\CampaignContactStatus;
use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Enums\VoiceCallStatus;
use App\Enums\VoiceIvrAction;
use App\Jobs\DispatchCampaignJob;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\ContactSuppression;
use App\Models\VoiceCall;
use App\Models\VoiceCallEvent;
use App\Services\CampaignDispatcher;
use App\Services\CampaignService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Queue::fake();
    config()->set('campaigns.require_approval', false);
    config()->set('campaigns.require_approved_audio', false);
    config()->set('services.voice.outbound_caller_id', '+233200000000');
    config()->set('voice.webhook.secret', 'switch-secret');

    [$this->client, $this->wallet, $this->user] = clientWithWallet(balance: 20.0);

    $service = app(CampaignService::class);

    $list = ContactList::factory()->forClient($this->client)->create();
    $contact = Contact::factory()->forClient($this->client)->create(['phone' => '+233244000001']);
    $list->contacts()->attach($contact->id, ['added_at' => now()]);

    $campaign = $service->create([
        'client_id' => $this->client->id,
        'name' => 'Reminder call',
        'channel' => Channel::Voice,
        'contact_list_id' => $list->id,
        'voice_script' => 'Your statement is ready.',
        'ivr_enabled' => true,
        'ivr_prompt' => 'Press 9 to stop these calls.',
    ], $this->user);

    $service->submit($campaign, $this->user);
    $service->launch($campaign->refresh());

    app(CampaignDispatcher::class)->run($campaign->refresh());

    $this->voiceCall = VoiceCall::query()->where('campaign_id', $campaign->id)->sole();
    $this->campaign = $campaign->refresh();
    $this->contact = $contact->refresh();

    /**
     * Post a signed callback the way the switch does.
     *
     * @param  array<string, mixed>  $payload
     */
    $this->postCallback = function (array $payload, ?string $secret = 'switch-secret'): TestResponse {
        $body = json_encode([...$payload]);

        return $this->call(
            'POST',
            route('webhooks.voice'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_OMNICAST_SIGNATURE' => $secret === null ? '' : hash_hmac('sha256', $body, $secret),
            ],
            $body,
        );
    };
});

it('rejects a callback that is not signed with the shared secret', function (): void {
    ($this->postCallback)(['reference' => $this->voiceCall->reference, 'event' => 'ringing'], null)
        ->assertForbidden();

    ($this->postCallback)(['reference' => $this->voiceCall->reference, 'event' => 'ringing'], 'wrong-secret')
        ->assertForbidden();

    config()->set('voice.webhook.secret', null);

    ($this->postCallback)(['reference' => $this->voiceCall->reference, 'event' => 'ringing'])
        ->assertForbidden();

    expect($this->voiceCall->refresh()->status)->toBe(VoiceCallStatus::Initiated)
        ->and(VoiceCallEvent::query()->count())->toBe(0);
});

it('moves a call through ringing, answering and the hang-up', function (): void {
    ($this->postCallback)(['reference' => $this->voiceCall->reference, 'event' => 'ringing'])
        ->assertAccepted();

    expect($this->voiceCall->refresh()->status)->toBe(VoiceCallStatus::Ringing);

    ($this->postCallback)([
        'reference' => $this->voiceCall->reference,
        'event' => 'answered',
        'event_id' => 'evt-answered',
    ])->assertAccepted();

    expect($this->voiceCall->refresh()->status)->toBe(VoiceCallStatus::Answered)
        ->and($this->voiceCall->answered_at)->not->toBeNull();

    ($this->postCallback)([
        'reference' => $this->voiceCall->reference,
        'event' => 'completed',
        'hangup_cause' => 'NORMAL_CLEARING',
        'duration' => 42,
    ])->assertAccepted();

    $this->voiceCall->refresh();

    expect($this->voiceCall->status)->toBe(VoiceCallStatus::Completed)
        ->and($this->voiceCall->duration_seconds)->toBe(42)
        ->and($this->voiceCall->ended_at)->not->toBeNull()
        ->and($this->voiceCall->events()->count())->toBe(3);
});

it('ignores a callback the switch already delivered', function (): void {
    $payload = [
        'reference' => $this->voiceCall->reference,
        'event' => 'answered',
        'event_id' => 'evt-duplicate',
    ];

    ($this->postCallback)($payload)->assertAccepted();
    ($this->postCallback)($payload)->assertAccepted();

    expect($this->voiceCall->events()->where('type', 'answered')->count())->toBe(1);
});

it('records a key press and honours the opt-out digit', function (): void {
    ($this->postCallback)([
        'reference' => $this->voiceCall->reference,
        'event' => 'dtmf',
        'digits' => '9',
    ])->assertAccepted();

    $event = $this->voiceCall->events()->where('type', 'dtmf')->sole();

    expect($event->digit)->toBe('9')
        ->and($event->ivr_action)->toBe(VoiceIvrAction::OptOut->value)
        ->and($this->voiceCall->refresh()->ivr_action)->toBe(VoiceIvrAction::OptOut->value)
        ->and($this->voiceCall->dtmf_digits)->toBe('9')
        ->and($this->contact->refresh()->dnd)->toBeTrue()
        ->and(ContactSuppression::query()
            ->where('client_id', $this->client->id)
            ->where('identifier', mb_strtolower($this->contact->phone))
            ->where('is_active', true)
            ->exists())->toBeTrue();
});

it('redials a recipient whose call was never answered', function (): void {
    ($this->postCallback)([
        'reference' => $this->voiceCall->reference,
        'event' => 'completed',
        'hangup_cause' => 'NO_ANSWER',
    ])->assertAccepted();

    expect($this->voiceCall->refresh()->status)->toBe(VoiceCallStatus::NoAnswer)
        ->and($this->voiceCall->campaignContact?->status)->toBe(CampaignContactStatus::Queued)
        ->and($this->campaign->refresh()->status)->toBe(CampaignStatus::Running);

    Queue::assertPushed(DispatchCampaignJob::class);
});

it('acknowledges a callback for a call it never dialled', function (): void {
    ($this->postCallback)(['reference' => 'not-ours', 'event' => 'ringing'])
        ->assertStatus(202)
        ->assertJson(['status' => 'ignored']);
});

it('rejects an event type it does not understand', function (): void {
    ($this->postCallback)(['reference' => $this->voiceCall->reference, 'event' => 'teleported'])
        ->assertStatus(422);
});
