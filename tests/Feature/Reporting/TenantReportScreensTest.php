<?php

use App\Enums\Channel;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\SmsMessage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

it('summarises the tenants own traffic on the overview', function (): void {
    [$client, , $user] = clientWithWallet();

    Campaign::factory()->for($client)->create(['channel' => Channel::Sms]);

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/index')
            ->where('summary.campaigns', 1)
            ->has('byChannel', count(Channel::cases()))
            ->has('monthly')
            ->has('topCampaigns')
            ->has('channels', count(Channel::cases()))
            ->where('can.export', true));
});

it('keeps another tenant out of the overview totals', function (): void {
    [$client, , $user] = clientWithWallet();
    [$otherClient] = clientWithWallet();

    Campaign::factory()->for($client)->create(['channel' => Channel::Sms]);
    Campaign::factory()->for($otherClient)->create(['channel' => Channel::Sms]);
    Campaign::factory()->for($otherClient)->create(['channel' => Channel::Voice]);

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.campaigns', 1)
            ->has('topCampaigns', 1));
});

it('lists one row per campaign on the campaign report', function (): void {
    [$client, , $user] = clientWithWallet();

    $campaign = Campaign::factory()->for($client)->create(['channel' => Channel::Sms]);
    SmsMessage::factory()->forCampaign($campaign)->delivered()->create();

    $this->actingAs($user)
        ->get(route('reports.campaigns'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/campaigns')
            ->has('report.rows', 1)
            ->where('report.rows.0.id', $campaign->id)
            ->has('campaigns', 1)
            ->has('report.totals'));
});

it('counts reachable contacts and opt outs on the contact report', function (): void {
    [$client, , $user] = clientWithWallet();

    Contact::factory()->forClient($client)->create(['dnd' => false]);
    Contact::factory()->forClient($client)->create(['dnd' => true, 'dnd_at' => now()->subDay()]);

    $this->actingAs($user)
        ->get(route('reports.contacts'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/contacts')
            ->where('report.totals.contacts', 2)
            ->where('report.totals.reachable', 1)
            ->where('report.totals.opt_outs', 1)
            ->has('report.byStatus'));
});

it('shows the channel screens only to signed-in users with the report permission', function (): void {
    [, , $user] = clientWithWallet();

    $this->actingAs($user)->get(route('reports.sms'))->assertOk();
    $this->actingAs($user)->get(route('reports.voice'))->assertOk();
    $this->actingAs($user)->get(route('reports.email'))->assertOk();

    $stranger = platformUser();
    $stranger->syncRoles([]);

    $this->actingAs($stranger)->get(route('reports.index'))->assertForbidden();
});
