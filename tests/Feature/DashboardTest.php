<?php

use App\Enums\CampaignContactStatus;
use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('guests are redirected from the admin dashboard to the login page', function () {
    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->forClient(Client::factory()->create())->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('dashboard'));
});

test('platform users can visit the admin dashboard path', function () {
    $this->actingAs(platformUser())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/dashboard'));
});

test('tenant users cannot visit the admin dashboard path', function () {
    $user = User::factory()->forClient(Client::factory()->create())->create();

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('platform users see campaign trends and delivery breakdowns', function () {
    $this->travelTo(Carbon::parse('2025-03-10 12:00:00'));

    $activeClient = Client::factory()->create();
    $pendingClient = Client::factory()->pending()->create();
    $pendingCampaign = Campaign::factory()
        ->forClient($activeClient)
        ->pendingApproval()
        ->create(['created_at' => now()]);
    Campaign::factory()->forClient($activeClient)->create([
        'channel' => Channel::Voice,
        'created_at' => now()->subDays(2),
        'status' => CampaignStatus::Completed,
    ]);
    Campaign::factory()->forClient($activeClient)->create([
        'channel' => Channel::MultiChannel,
        'created_at' => now()->subDays(8),
        'status' => CampaignStatus::Running,
    ]);

    $deliveredContact = Contact::factory()->for($activeClient)->create();
    $failedContact = Contact::factory()->for($activeClient)->create();
    CampaignContact::factory()->for($pendingCampaign)->for($deliveredContact)->create([
        'status' => CampaignContactStatus::Delivered,
    ]);
    CampaignContact::factory()->for($pendingCampaign)->for($failedContact)->create([
        'status' => CampaignContactStatus::Failed,
    ]);

    $response = $this->actingAs(platformUser())->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->where('stats.clients', 2)
            ->where('stats.pending_clients', 1)
            ->where('stats.campaigns', 3)
            ->where('stats.pending_campaigns', 1)
            ->where('stats.active_campaigns', 1)
            ->has('campaignActivity', 7)
            ->where('campaignActivity.4.total', 1)
            ->where('campaignActivity.6.total', 1)
            ->where('campaignStatusBreakdown', [
                ['status' => 'pending_approval', 'label' => 'Pending Approval', 'total' => 1],
                ['status' => 'running', 'label' => 'Running', 'total' => 1],
                ['status' => 'completed', 'label' => 'Completed', 'total' => 1],
            ])
            ->where('channelBreakdown', [
                ['channel' => 'voice', 'label' => 'Voice', 'total' => 1],
                ['channel' => 'sms', 'label' => 'SMS', 'total' => 1],
                ['channel' => 'email', 'label' => 'Email', 'total' => 0],
                ['channel' => 'multi_channel', 'label' => 'Multi-Channel', 'total' => 1],
            ])
            ->where('recipientStatusBreakdown', [
                ['status' => 'delivered', 'label' => 'Delivered', 'total' => 1],
                ['status' => 'failed', 'label' => 'Failed', 'total' => 1],
            ])
            ->where('recentCampaigns.0.client_name', $activeClient->name));
});
