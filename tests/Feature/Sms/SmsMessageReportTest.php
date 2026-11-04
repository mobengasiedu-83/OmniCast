<?php

use App\Enums\Channel;
use App\Models\Campaign;
use App\Models\SmsMessage;
use App\Services\SmsMessageReport;
use Inertia\Testing\AssertableInertia as Assert;

it('summarises delivery rate, awaiting receipts, segments and spend', function (): void {
    [$client] = clientWithWallet();

    $campaign = Campaign::factory()->for($client)->create(['channel' => Channel::Sms]);

    SmsMessage::query()->create([
        ...SmsMessage::factory()->forCampaign($campaign)->delivered()->make()->toArray(),
        'segments' => 2,
        'cost' => 0.05,
    ]);
    SmsMessage::query()->create([
        ...SmsMessage::factory()->forCampaign($campaign)->undelivered()->make()->toArray(),
        'cost' => 0.05,
    ]);
    SmsMessage::query()->create([
        ...SmsMessage::factory()->forCampaign($campaign)->make()->toArray(),
        'cost' => 0.05,
    ]);

    $report = app(SmsMessageReport::class)->forCampaign($campaign);

    expect($report['totals']['messages'])->toBe(3)
        ->and($report['totals']['delivered'])->toBe(1)
        ->and($report['totals']['pending'])->toBe(1)
        ->and($report['totals']['undelivered'])->toBe(1)
        ->and($report['totals']['delivery_rate'])->toBe(33.3)
        ->and($report['totals']['segments'])->toBe(4)
        ->and($report['totals']['spend'])->toBe(0.15)
        ->and($report['recent'])->toHaveCount(3);
});

it('shows the sms delivery report to a tenant user', function (): void {
    [$client, , $user] = clientWithWallet();

    $campaign = Campaign::factory()->for($client)->create(['channel' => Channel::Sms]);
    SmsMessage::factory()->forCampaign($campaign)->delivered()->create();

    $this->actingAs($user)
        ->get(route('reports.sms', ['campaign' => $campaign->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/sms')
            ->where('report.totals.messages', 1)
            ->where('report.totals.delivered', 1)
            ->where('filters.campaign', $campaign->id)
            ->where('campaigns.0.id', $campaign->id));
});

it('keeps another tenant out of the sms delivery report', function (): void {
    [$client, , $user] = clientWithWallet();
    [$otherClient] = clientWithWallet();

    SmsMessage::factory()
        ->forCampaign(Campaign::factory()->for($client)->create(['channel' => Channel::Sms]))
        ->delivered()
        ->create();

    SmsMessage::factory()
        ->forCampaign(Campaign::factory()->for($otherClient)->create(['channel' => Channel::Sms]))
        ->delivered()
        ->create();

    $this->actingAs($user)
        ->get(route('reports.sms'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/sms')
            ->where('report.totals.messages', 1));
});

it('shows the sms panel only on campaigns that send sms', function (): void {
    [$client, , $user] = clientWithWallet();

    $sms = Campaign::factory()->for($client)->create(['channel' => Channel::Sms]);
    SmsMessage::factory()->forCampaign($sms)->delivered()->create();

    $voice = Campaign::factory()->for($client)->create(['channel' => Channel::Voice]);

    $this->actingAs($user)
        ->get(route('campaigns.show', $sms))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->where('smsReport.totals.messages', 1));

    $this->actingAs($user)
        ->get(route('campaigns.show', $voice))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->where('smsReport', null));
});
