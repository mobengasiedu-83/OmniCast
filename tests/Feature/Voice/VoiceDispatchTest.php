<?php

use App\Contracts\VoiceGateway;
use App\Enums\CampaignContactStatus;
use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Enums\VoiceCallStatus;
use App\Enums\VoiceIvrAction;
use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\PlatformSetting;
use App\Models\VoiceCall;
use App\Services\CampaignDispatcher;
use App\Services\CampaignService;
use App\Services\Voice\AsteriskAmiGateway;
use App\Services\Voice\DisabledVoiceGateway;
use App\Services\Voice\VoiceGatewayManager;
use App\Services\VoiceIvr;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    config()->set('campaigns.require_approval', false);
    config()->set('campaigns.require_approved_audio', false);
    config()->set('services.voice.outbound_caller_id', '+233200000000');

    [$this->client, $this->wallet, $this->user] = clientWithWallet(balance: 20.0);

    $this->service = app(CampaignService::class);

    $list = ContactList::factory()->forClient($this->client)->create();
    $contacts = Contact::factory()->count(2)->forClient($this->client)->create();
    $list->contacts()->attach($contacts->pluck('id'), ['added_at' => now()]);

    $this->voiceCampaign = function (array $attributes = []) use ($list): Campaign {
        $campaign = $this->service->create([
            'client_id' => $this->client->id,
            'name' => 'Reminder call',
            'channel' => Channel::Voice,
            'contact_list_id' => $list->id,
            'voice_script' => 'Your statement is ready.',
            ...$attributes,
        ], $this->user);

        $this->service->submit($campaign, $this->user);
        $this->service->launch($campaign->refresh());

        return $campaign->refresh();
    };
});

it('logs a voice call per recipient with the ivr plan frozen on the call', function (): void {
    $campaign = ($this->voiceCampaign)([
        'ivr_enabled' => true,
        'ivr_prompt' => 'Press 1 to hear the message.',
        'ivr_options' => ['1' => 'callback'],
    ]);

    app(CampaignDispatcher::class)->run($campaign);

    $calls = VoiceCall::query()->where('campaign_id', $campaign->id)->get();

    expect($calls)->toHaveCount(2)
        ->and($calls->pluck('status')->unique()->all())->toBe([VoiceCallStatus::Initiated])
        ->and($calls->pluck('provider')->unique()->all())->toBe(['log'])
        ->and($calls->pluck('from_number')->unique()->all())->toBe(['+233200000000'])
        ->and($calls->every(fn (VoiceCall $call): bool => $call->provider_call_id !== null))->toBeTrue()
        ->and($calls->first()->last_event['ivr']['options'])->toMatchArray([
            '1' => VoiceIvrAction::Callback->value,
            '9' => VoiceIvrAction::OptOut->value,
        ])
        ->and($campaign->refresh()->status)->toBe(CampaignStatus::Completed)
        ->and(CampaignContact::query()->where('campaign_id', $campaign->id)->pluck('status')->unique()->all())
        ->toBe([CampaignContactStatus::Delivered]);
});

it('always keeps the opt-out key on the campaign menu', function (): void {
    $campaign = ($this->voiceCampaign)([
        'ivr_enabled' => true,
        'ivr_prompt' => 'Press 1 for the message.',
        'ivr_options' => ['1' => 'repeat', '9' => 'connect'],
    ]);

    $plan = app(VoiceIvr::class)->planFor($campaign);

    expect($plan)->not->toBeNull()
        ->and($plan?->options['1'])->toBe(VoiceIvrAction::Repeat)
        ->and($plan?->options['9'])->toBe(VoiceIvrAction::OptOut);
});

it('plays no menu when the campaign has none', function (): void {
    $campaign = ($this->voiceCampaign)();

    expect(app(VoiceIvr::class)->planFor($campaign))->toBeNull();
});

it('refuses to dial through the pbx while live broadcasting is switched off', function (): void {
    config()->set('voice.broadcast.enabled', false);

    PlatformSetting::query()->create(['group' => 'voice', 'key' => 'driver', 'value' => 'ami']);

    expect(app(VoiceGatewayManager::class)->driver())->toBeInstanceOf(DisabledVoiceGateway::class);

    config()->set('voice.broadcast.enabled', true);

    expect(app(VoiceGatewayManager::class)->driver())->toBeInstanceOf(AsteriskAmiGateway::class);
});

it('queues the recipient for another attempt when the switch refuses the call', function (): void {
    $campaign = ($this->voiceCampaign)();

    $this->app->instance(VoiceGateway::class, new DisabledVoiceGateway('Voice broadcasting is disabled.'));

    $stillRetrying = app(CampaignDispatcher::class)->run($campaign);

    $call = VoiceCall::query()->where('campaign_id', $campaign->id)->first();
    $recipient = CampaignContact::query()->where('campaign_id', $campaign->id)->first();

    expect($stillRetrying)->toBeTrue()
        ->and($call?->status)->toBe(VoiceCallStatus::Failed)
        ->and($call?->error_message)->toContain('disabled')
        ->and($recipient?->status)->toBe(CampaignContactStatus::Queued)
        ->and($recipient?->error_message)->toBe('The switch did not accept the call.')
        ->and($campaign->refresh()->status)->toBe(CampaignStatus::Running);
});
