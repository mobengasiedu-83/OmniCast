<?php

use App\Enums\Channel;
use App\Models\Campaign;
use App\Models\EmailMessage;
use App\Models\SmsMessage;
use App\Models\VoiceCall;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

it('summarises every client for the executive report', function (): void {
    [$clientA] = clientWithWallet();
    [$clientB] = clientWithWallet();
    $admin = platformUser();

    Campaign::factory()->for($clientA)->create(['channel' => Channel::Sms]);
    Campaign::factory()->for($clientB)->create(['channel' => Channel::Voice]);

    $this->actingAs($admin)
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/index')
            ->where('summary.clients', 2)
            ->where('summary.campaigns', 2)
            ->has('byChannel', count(Channel::cases()))
            ->has('monthly')
            ->has('topClients')
            ->has('trend')
            ->has('clients')
            ->where('filters.client_id', null)
            ->where('can.export', true));
});

it('counts each channel from what the provider confirmed', function (): void {
    [$client] = clientWithWallet();
    $admin = platformUser();

    $sms = Campaign::factory()->for($client)->create(['channel' => Channel::Sms]);
    SmsMessage::factory()->forCampaign($sms)->delivered()->create();
    SmsMessage::factory()->forCampaign($sms)->undelivered()->create();

    $voice = Campaign::factory()->for($client)->create(['channel' => Channel::Voice]);
    VoiceCall::factory()->forCampaign($voice)->answered()->create();
    VoiceCall::factory()->forCampaign($voice)->noAnswer()->create();

    $email = Campaign::factory()->for($client)->create(['channel' => Channel::Email]);
    EmailMessage::factory()->forCampaign($email)->delivered()->create();
    EmailMessage::factory()->forCampaign($email)->bounced()->create();

    $this->actingAs($admin)
        ->get(route('admin.reports.sms'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/channel')
            ->where('channel.value', 'sms')
            ->where('totals.total', 2)
            ->where('totals.delivered', 1)
            ->where('totals.failed', 1)
            ->where('totals.delivery_rate', 50)
            ->where('byClient.0.name', $client->name)
            ->where('byClient.0.total', 2));

    $this->actingAs($admin)
        ->get(route('admin.reports.voice'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/channel')
            ->where('channel.value', 'voice')
            ->where('channel.unit', 'Calls')
            ->where('totals.total', 2)
            ->where('totals.delivered', 1)
            ->where('totals.failed', 1));

    $this->actingAs($admin)
        ->get(route('admin.reports.email'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/channel')
            ->where('channel.value', 'email')
            ->where('channel.unit', 'Emails')
            ->where('totals.total', 2)
            ->where('totals.delivered', 1)
            ->where('totals.failed', 1));
});

it('narrows a channel screen to one client when the operator picks one', function (): void {
    [$clientA] = clientWithWallet();
    [$clientB] = clientWithWallet();
    $admin = platformUser();

    foreach ([$clientA, $clientB] as $client) {
        $campaign = Campaign::factory()->for($client)->create(['channel' => Channel::Sms]);
        SmsMessage::factory()->forCampaign($campaign)->delivered()->create();
    }

    $this->actingAs($admin)
        ->get(route('admin.reports.sms', ['client_id' => $clientB->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.client_id', $clientB->id)
            ->where('totals.total', 1)
            ->has('byClient', 1));
});

it('renders the client, revenue and provider screens for the platform team', function (): void {
    [$client] = clientWithWallet();
    $admin = platformUser();

    Campaign::factory()->for($client)->create(['channel' => Channel::Sms]);

    $this->actingAs($admin)
        ->get(route('admin.reports.clients'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/clients')
            ->where('report.totals.clients', 1)
            ->has('report.rows', 1)
            ->where('report.rows.0.name', $client->name));

    $this->actingAs($admin)
        ->get(route('admin.reports.revenue'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/revenue')
            ->where('totals.revenue', 0)
            ->where('totals.payments', 0)
            ->has('wallet')
            ->has('monthly')
            ->has('byProvider'));

    $this->actingAs($admin)
        ->get(route('admin.reports.providers'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/providers')
            ->has('sms.rows')
            ->has('voice.rows')
            ->has('email.rows')
            ->where('reconciliation.balanced', true)
            ->has('reconciliation.usage'));
});

it('keeps client users out of every platform report', function (): void {
    [, , $user] = clientWithWallet();

    foreach ([
        'admin.reports.index',
        'admin.reports.clients',
        'admin.reports.revenue',
        'admin.reports.providers',
        'admin.reports.voice',
        'admin.reports.sms',
        'admin.reports.email',
        'admin.reports.exports.index',
    ] as $route) {
        $this->actingAs($user)->get(route($route))->assertForbidden();
    }
});
