<?php

use App\Enums\CampaignContactStatus;
use App\Enums\Channel;
use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\ReportDailyStat;
use Illuminate\Support\Carbon;

it('rolls a day up from the recipients that moved', function (): void {
    [$client] = clientWithWallet();

    $campaign = Campaign::factory()->for($client)->create(['channel' => Channel::Sms]);

    CampaignContact::factory()->for($campaign)->create([
        'status' => CampaignContactStatus::Delivered,
        'cost' => 0.05,
    ]);
    CampaignContact::factory()->for($campaign)->create([
        'status' => CampaignContactStatus::Failed,
        'cost' => 0.05,
    ]);
    CampaignContact::factory()->for($campaign)->create([
        'status' => CampaignContactStatus::Queued,
    ]);

    $this->artisan('reports:aggregate', ['--days' => 1])->assertSuccessful();

    $stat = ReportDailyStat::query()->where('client_id', $client->id)->sole();

    expect($stat->date->toDateString())->toBe(now()->toDateString())
        ->and($stat->channel)->toBe(Channel::Sms->value)
        ->and($stat->campaigns)->toBe(1)
        ->and($stat->recipients)->toBe(3)
        ->and($stat->delivered)->toBe(1)
        ->and($stat->failed)->toBe(1)
        ->and($stat->skipped)->toBe(0)
        ->and((float) $stat->spend)->toBe(0.1);
});

it('rewrites a day instead of doubling it on a second run', function (): void {
    [$client] = clientWithWallet();

    $campaign = Campaign::factory()->for($client)->create(['channel' => Channel::Voice]);

    CampaignContact::factory()->for($campaign)->create([
        'status' => CampaignContactStatus::Delivered,
        'cost' => 0.2,
    ]);

    $this->artisan('reports:aggregate')->assertSuccessful();
    $this->artisan('reports:aggregate')->assertSuccessful();

    expect(ReportDailyStat::query()->count())->toBe(1)
        ->and(ReportDailyStat::query()->sole()->recipients)->toBe(1);
});

it('rebuilds one named day and forgets a day that lost its recipients', function (): void {
    [$client] = clientWithWallet();

    $campaign = Campaign::factory()->for($client)->create(['channel' => Channel::Email]);

    $contact = CampaignContact::factory()->for($campaign)->create([
        'status' => CampaignContactStatus::Delivered,
        'cost' => 0.01,
    ]);
    $contact->forceFill(['created_at' => Carbon::parse('2026-11-01 10:00:00')])->saveQuietly();

    $this->artisan('reports:aggregate', ['--date' => '2026-11-01'])->assertSuccessful();

    expect(ReportDailyStat::query()->whereDate('date', '2026-11-01')->count())->toBe(1)
        ->and(ReportDailyStat::query()->whereDate('date', now()->toDateString())->count())->toBe(0);

    CampaignContact::query()->delete();

    $this->artisan('reports:aggregate', ['--date' => '2026-11-01'])->assertSuccessful();

    expect(ReportDailyStat::query()->count())->toBe(0);
});
