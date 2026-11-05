<?php

use App\Enums\CampaignContactStatus;
use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Jobs\DispatchCampaignJob;
use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\Client;
use App\Services\Delivery\DeliveryPacer;
use App\Services\Delivery\QueueTopology;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Inertia\Testing\AssertableInertia as Assert;

it('routes campaign work to the queue that owns the channel', function (): void {
    $queues = app(QueueTopology::class);

    $sms = Campaign::factory()->create(['channel' => Channel::Sms]);
    $multiChannel = Campaign::factory()->create(['channel' => Channel::MultiChannel]);

    expect($queues->queueForCampaign($sms))->toBe('sms')
        ->and($queues->queueForCampaign($multiChannel))->toBe(config('queues.campaigns'))
        ->and($queues->queuesFor($multiChannel))->toBe(['voice', 'sms']);
});

it('describes every queue with its supervisor and retry policy', function (): void {
    $queues = app(QueueTopology::class)->queues();

    expect(array_column($queues, 'name'))->toBe(['campaigns', 'sms', 'voice', 'email', 'default'])
        ->and($queues[0]['supervisor'])->not->toBeNull()
        ->and($queues[1]['policy']['tries'])->toBeInt()
        ->and($queues[1]['cps'])->toBeInt();
});

it('releases a campaign whose scheduled start time has passed', function (): void {
    Queue::fake();

    $due = Campaign::factory()->create([
        'status' => CampaignStatus::Scheduled,
        'scheduled_at' => now()->subMinute(),
    ]);
    $later = Campaign::factory()->create([
        'status' => CampaignStatus::Scheduled,
        'scheduled_at' => now()->addHour(),
    ]);

    $this->artisan('campaigns:dispatch-scheduled')->assertSuccessful();

    expect($due->refresh()->status)->toBe(CampaignStatus::Running)
        ->and($due->launched_at)->not->toBeNull()
        ->and($later->refresh()->status)->toBe(CampaignStatus::Scheduled);

    Queue::assertPushedOn('sms', DispatchCampaignJob::class);
    Queue::assertPushed(DispatchCampaignJob::class, 1);
});

it('re-queues a running campaign that stopped making progress', function (): void {
    Queue::fake();

    $stalled = Campaign::factory()->create([
        'status' => CampaignStatus::Running,
        'retry_delay_minutes' => 5,
    ]);
    CampaignContact::factory()->for($stalled)->create([
        'status' => CampaignContactStatus::Pending,
    ]);

    $stalled->forceFill(['updated_at' => now()->subMinutes(30)])->saveQuietly();

    $fresh = Campaign::factory()->create(['status' => CampaignStatus::Running]);
    CampaignContact::factory()->for($fresh)->create([
        'status' => CampaignContactStatus::Pending,
    ]);

    $this->artisan('campaigns:sweep')->assertSuccessful();

    Queue::assertPushed(DispatchCampaignJob::class, 1);
    Queue::assertPushed(
        DispatchCampaignJob::class,
        fn (DispatchCampaignJob $job): bool => $job->campaignId === $stalled->id,
    );
});

it('holds a channel to the submissions per second it was given', function (): void {
    Sleep::fake();

    config()->set('queues.channels.sms.cps', 1);

    $pacer = app(DeliveryPacer::class);

    $pacer->await(Channel::Sms);
    $pacer->await(Channel::Sms);

    Sleep::assertSleptTimes(1);
});

it('resolves the throughput ceiling from the config default then the client override', function (): void {
    $pacer = app(DeliveryPacer::class);

    expect($pacer->perSecond(Channel::Sms))->toBe(0);

    config()->set('queues.channels.sms.cps', 25);
    $pacer->reset();

    expect($pacer->perSecond(Channel::Sms))->toBe(25);

    $client = Client::factory()->create([
        'settings' => ['overrides' => ['rate_limits.sms_cps' => 7]],
    ]);

    expect($pacer->perSecond(Channel::Sms, $client))->toBe(7);
});

it('shows the queue topology to platform administrators', function (): void {
    $admin = platformUser();

    $this->actingAs($admin)
        ->withoutVite()
        ->get(route('admin.queues.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/queues/index')
            ->where('connection.name', config('queue.default'))
            ->has('queues', count(app(QueueTopology::class)->queues()))
            ->where('queues.0.name', config('queues.campaigns'))
            ->has('channels', 3)
            ->where('channels.0.queue', app(QueueTopology::class)->queueForChannel(Channel::Sms))
            ->has('totals.queued')
            ->has('totals.failed'));
});

it('forbids client users from the queue topology', function (): void {
    [, , $clientUser] = clientWithWallet();

    $this->actingAs($clientUser)
        ->get(route('admin.queues.index'))
        ->assertForbidden();
});

it('opens the horizon dashboard only to the platform team', function (): void {
    $admin = platformUser();
    [, , $clientUser] = clientWithWallet();

    expect(Gate::forUser($admin)->allows('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser($clientUser)->allows('viewHorizon'))->toBeFalse();
});
