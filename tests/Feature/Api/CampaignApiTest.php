<?php

use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Models\Campaign;

beforeEach(function (): void {
    config()->set('api.version', 'v1');
    config()->set('api.pagination.per_page', 2);

    [$this->client, $this->wallet, $this->user] = clientWithWallet();
});

it('answers a health probe without a token', function (): void {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJson(['status' => 'ok', 'version' => 'v1']);
});

it('refuses the campaign list without a token', function (): void {
    $this->getJson('/api/v1/campaigns')->assertUnauthorized();
});

it('lists only the campaigns of the token tenant', function (): void {
    $mine = Campaign::factory()->for($this->client)->create([
        'name' => 'Mine',
        'channel' => Channel::Sms,
        'status' => CampaignStatus::Draft,
    ]);

    $other = clientWithWallet()[0];

    Campaign::factory()->for($other)->create([
        'name' => 'Theirs',
        'channel' => Channel::Sms,
        'status' => CampaignStatus::Draft,
    ]);

    $token = $this->user->createToken('reporting', ['read'])->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/campaigns')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Mine')
        ->assertJsonPath('data.0.links.self', route('api.v1.campaigns.show', ['campaign' => $mine->id]))
        ->assertJsonStructure([
            'data' => [['id', 'reference', 'name', 'channel', 'status', 'statistics', 'cost', 'timestamps', 'links']],
            'links',
            'meta',
        ]);

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('meta.per_page'))->toBe(2);
});

it('filters the list by status and search term', function (): void {
    Campaign::factory()->for($this->client)->create([
        'name' => 'April promo',
        'channel' => Channel::Sms,
        'status' => CampaignStatus::Draft,
    ]);

    Campaign::factory()->for($this->client)->create([
        'name' => 'May promo',
        'channel' => Channel::Sms,
        'status' => CampaignStatus::Running,
    ]);

    $token = $this->user->createToken('reporting', ['read'])->plainTextToken;
    $headers = ['Authorization' => 'Bearer '.$token];

    $this->withHeaders($headers)
        ->getJson('/api/v1/campaigns?status=running')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'May promo');

    $this->withHeaders($headers)
        ->getJson('/api/v1/campaigns?search=April')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'April promo');
});

it('shows one campaign with its recipient counts', function (): void {
    $campaign = Campaign::factory()->for($this->client)->create([
        'name' => 'Statement',
        'channel' => Channel::Sms,
        'status' => CampaignStatus::Completed,
    ]);

    $token = $this->user->createToken('reporting', ['read'])->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/campaigns/'.$campaign->id)
        ->assertOk()
        ->assertJsonPath('data.id', $campaign->id)
        ->assertJsonPath('data.name', 'Statement')
        ->assertJsonPath('data.statistics.recipients', 0)
        ->assertJsonPath('data.status', CampaignStatus::Completed->value);
});

it('hides another tenant campaign behind a 404', function (): void {
    $other = clientWithWallet()[0];

    $campaign = Campaign::factory()->for($other)->create([
        'channel' => Channel::Sms,
        'status' => CampaignStatus::Draft,
    ]);

    $token = $this->user->createToken('reporting', ['read'])->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/campaigns/'.$campaign->id)
        ->assertNotFound();
});

it('refuses a token without the read ability', function (): void {
    $token = $this->user->createToken('script', ['write'])->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/campaigns')
        ->assertForbidden();
});
