<?php

use App\Services\Operations\OperationalHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

it('reports dependency health through a public readiness endpoint without diagnostic details', function (): void {
    config()->set('queue.default', 'database');

    $this->getJson(route('api.v1.health.ready'))
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonStructure([
            'status',
            'checked_at',
            'checks' => [['name', 'status']],
        ])
        ->assertJsonMissingPath('checks.0.message');
});

it('returns unavailable when a required dependency probe fails', function (): void {
    DB::shouldReceive('connection')->andThrow(new RuntimeException('database credentials must not be exposed'));

    $this->getJson(route('api.v1.health.ready'))
        ->assertServiceUnavailable()
        ->assertJsonPath('status', 'unavailable')
        ->assertJsonMissing(['database credentials must not be exposed']);
});

it('shows system health and runtime details to platform queue operators', function (): void {
    config()->set('queue.default', 'database');

    $this->actingAs(platformUser())
        ->withoutVite()
        ->get(route('admin.system.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/system/index')
            ->where('health.status', 'ok')
            ->has('health.checks', 5)
            ->where('runtime.queue_driver', 'database')
            ->has('runtime.php')
            ->has('runtime.laravel'));
});

it('denies system health details to client accounts', function (): void {
    [, , $clientUser] = clientWithWallet();

    $this->actingAs($clientUser)
        ->get(route('admin.system.index'))
        ->assertForbidden();
});

it('allows queue operators to download the staff-only runbook', function (): void {
    $this->actingAs(platformUser())
        ->get(route('admin.system.runbook'))
        ->assertOk()
        ->assertDownload('OmniCast-Operations-Runbook.md');
});

it('reports the live Horizon check as not applicable for a non-Redis queue', function (): void {
    config()->set('queue.default', 'database');

    $report = app(OperationalHealth::class)->report();

    expect(collect($report['checks'])->firstWhere('key', 'horizon')['status'])
        ->toBe('not_applicable');
});

it('marks readiness unavailable when Redis has no running Horizon master', function (): void {
    config()->set('queue.default', 'redis');

    $queueConnection = new class
    {
        public function size(?string $queue = null): int
        {
            return 0;
        }
    };

    Queue::shouldReceive('connection')->andReturn($queueConnection);

    $this->mock(MasterSupervisorRepository::class, function ($mock): void {
        $mock->shouldReceive('all')->once()->andReturn([]);
    });

    $this->getJson(route('api.v1.health.ready'))
        ->assertServiceUnavailable()
        ->assertJsonPath('status', 'unavailable')
        ->assertJsonPath('checks.4.name', 'horizon')
        ->assertJsonPath('checks.4.status', 'failed');
});

it('reports active Horizon masters and their supervisors for Redis queues', function (): void {
    config()->set('queue.default', 'redis');

    Queue::shouldReceive('connection')->andReturn(new class
    {
        public function size(?string $queue = null): int
        {
            return 0;
        }
    });

    $this->mock(MasterSupervisorRepository::class, function ($mock): void {
        $mock->shouldReceive('all')->once()->andReturn([
            (object) [
                'status' => 'running',
                'supervisors' => ['supervisor-email'],
            ],
        ]);
    });

    $report = app(OperationalHealth::class)->report();
    $horizon = collect($report['checks'])->firstWhere('key', 'horizon');

    expect($report['status'])->toBe('ok')
        ->and($horizon['status'])->toBe('ok')
        ->and($horizon['metrics']['supervisors'])->toBe(['supervisor-email']);
});
