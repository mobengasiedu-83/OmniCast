<?php

use App\Enums\Channel;
use App\Models\Campaign;
use App\Models\EmailMessage;
use App\Services\EmailMessageReport;
use Inertia\Testing\AssertableInertia as Assert;

it('summarises delivery rate, bounces, complaints and spend', function (): void {
    [$client] = clientWithWallet();

    $campaign = Campaign::factory()->for($client)->create(['channel' => Channel::Email]);

    EmailMessage::query()->create([
        ...EmailMessage::factory()->forCampaign($campaign)->delivered()->make()->toArray(),
        'cost' => 0.02,
    ]);
    EmailMessage::query()->create([
        ...EmailMessage::factory()->forCampaign($campaign)->bounced()->make()->toArray(),
        'cost' => 0.02,
    ]);
    EmailMessage::query()->create([
        ...EmailMessage::factory()->forCampaign($campaign)->complained()->make()->toArray(),
        'cost' => 0.02,
    ]);
    EmailMessage::query()->create([
        ...EmailMessage::factory()->forCampaign($campaign)->make()->toArray(),
        'cost' => 0.02,
    ]);

    $report = app(EmailMessageReport::class)->forCampaign($campaign);

    expect($report['totals']['messages'])->toBe(4)
        ->and($report['totals']['delivered'])->toBe(1)
        ->and($report['totals']['pending'])->toBe(1)
        ->and($report['totals']['bounced'])->toBe(1)
        ->and($report['totals']['complained'])->toBe(1)
        ->and($report['totals']['delivery_rate'])->toBe(25.0)
        ->and($report['totals']['bounce_rate'])->toBe(25.0)
        ->and($report['totals']['spend'])->toBe(0.08)
        ->and($report['recent'])->toHaveCount(4);
});

it('shows the email delivery report to a tenant user', function (): void {
    [$client, , $user] = clientWithWallet();

    $campaign = Campaign::factory()->for($client)->create(['channel' => Channel::Email]);
    EmailMessage::factory()->forCampaign($campaign)->delivered()->create();

    $this->actingAs($user)
        ->get(route('reports.email', ['campaign' => $campaign->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/email')
            ->where('report.totals.messages', 1)
            ->where('report.totals.delivered', 1)
            ->where('filters.campaign', $campaign->id)
            ->where('campaigns.0.id', $campaign->id));
});

it('keeps another tenant out of the email delivery report', function (): void {
    [$client, , $user] = clientWithWallet();
    [$otherClient] = clientWithWallet();

    EmailMessage::factory()
        ->forCampaign(Campaign::factory()->for($client)->create(['channel' => Channel::Email]))
        ->delivered()
        ->create();

    EmailMessage::factory()
        ->forCampaign(Campaign::factory()->for($otherClient)->create(['channel' => Channel::Email]))
        ->delivered()
        ->create();

    $this->actingAs($user)
        ->get(route('reports.email'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/email')
            ->where('report.totals.messages', 1));
});

it('shows the email panel only on campaigns that send email', function (): void {
    [$client, , $user] = clientWithWallet();

    $email = Campaign::factory()->for($client)->create(['channel' => Channel::Email]);
    EmailMessage::factory()->forCampaign($email)->delivered()->create();

    $sms = Campaign::factory()->for($client)->create(['channel' => Channel::Sms]);

    $this->actingAs($user)
        ->get(route('campaigns.show', $email))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->where('emailReport.totals.messages', 1));

    $this->actingAs($user)
        ->get(route('campaigns.show', $sms))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->where('emailReport', null));
});
