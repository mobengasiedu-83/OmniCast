<?php

use App\Enums\Channel;
use App\Enums\VoiceCallEventType;
use App\Enums\VoiceIvrAction;
use App\Models\Campaign;
use App\Models\VoiceCall;
use App\Models\VoiceCallEvent;
use App\Services\VoiceCallReport;
use Inertia\Testing\AssertableInertia as Assert;

it('summarises answer rate, talk time, spend and key presses', function (): void {
    [$client] = clientWithWallet();

    $campaign = Campaign::factory()->for($client)->create(['channel' => Channel::Voice]);

    $answered = VoiceCall::query()->create([
        ...VoiceCall::factory()->forCampaign($campaign)->answered(40)->make()->toArray(),
        'cost' => 0.6,
    ]);
    VoiceCall::query()->create([
        ...VoiceCall::factory()->forCampaign($campaign)->noAnswer()->make()->toArray(),
        'cost' => 0.4,
    ]);

    VoiceCallEvent::query()->create([
        'voice_call_id' => $answered->id,
        'type' => VoiceCallEventType::Dtmf,
        'digit' => '1',
        'ivr_action' => VoiceIvrAction::Callback->value,
        'occurred_at' => now(),
    ]);

    $report = app(VoiceCallReport::class)->forCampaign($campaign);

    expect($report['totals']['calls'])->toBe(2)
        ->and($report['totals']['answered'])->toBe(1)
        ->and($report['totals']['no_answer'])->toBe(1)
        ->and($report['totals']['answer_rate'])->toBe(50.0)
        ->and($report['totals']['average_duration'])->toBe(40)
        ->and($report['totals']['spend'])->toBe(1.0)
        ->and($report['digits'][0]['digit'])->toBe('1')
        ->and($report['digits'][0]['action_label'])->toBe(VoiceIvrAction::Callback->label())
        ->and($report['recent'])->toHaveCount(2);
});

it('shows the voice call report to a tenant user', function (): void {
    [$client, , $user] = clientWithWallet();

    $campaign = Campaign::factory()->for($client)->create(['channel' => Channel::Voice]);
    VoiceCall::factory()->forCampaign($campaign)->answered()->create();

    $this->actingAs($user)
        ->get(route('reports.voice', ['campaign' => $campaign->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/voice')
            ->where('report.totals.calls', 1)
            ->where('report.totals.answered', 1)
            ->where('filters.campaign', $campaign->id)
            ->where('campaigns.0.id', $campaign->id));
});

it('keeps another tenant out of the call report', function (): void {
    [$client, , $user] = clientWithWallet();
    [$otherClient] = clientWithWallet();

    VoiceCall::factory()
        ->forCampaign(Campaign::factory()->for($client)->create(['channel' => Channel::Voice]))
        ->answered()
        ->create();

    VoiceCall::factory()
        ->forCampaign(Campaign::factory()->for($otherClient)->create(['channel' => Channel::Voice]))
        ->answered()
        ->create();

    $this->actingAs($user)
        ->get(route('reports.voice'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/voice')
            ->where('report.totals.calls', 1));
});

it('shows the call panel only on campaigns that dial', function (): void {
    [$client, , $user] = clientWithWallet();

    $voice = Campaign::factory()->for($client)->create(['channel' => Channel::Voice]);
    VoiceCall::factory()->forCampaign($voice)->answered()->create();

    $sms = Campaign::factory()->for($client)->create(['channel' => Channel::Sms]);

    $this->actingAs($user)
        ->get(route('campaigns.show', $voice))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->where('voiceReport.totals.calls', 1));

    $this->actingAs($user)
        ->get(route('campaigns.show', $sms))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->where('voiceReport', null));
});
