<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Channel;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Services\Delivery\DeliveryPacer;
use App\Services\Delivery\QueueTopology;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * The delivery queues the orchestration layer drains.
 *
 * The page answers the two operational questions: is anything backing up, and
 * which worker pool and retry policy owns the backlog?
 */
class QueueController extends Controller
{
    public function __construct(
        protected QueueTopology $queues,
        protected DeliveryPacer $pacer,
    ) {}

    /**
     * Show the queue topology with its current depth.
     */
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::ViewQueues->value);

        $connection = (string) config('queue.default');
        $queues = array_map(fn (array $queue): array => $this->row($queue), $this->queues->queues());

        return Inertia::render('admin/queues/index', [
            'connection' => [
                'name' => $connection,
                'driver' => (string) config('queue.connections.'.$connection.'.driver', $connection),
                'horizon' => [
                    'path' => '/'.ltrim((string) config('horizon.path', 'horizon'), '/'),
                    'accessible' => app()->environment('local') || ($request->user()?->can('viewHorizon') ?? false),
                ],
                'scheduler' => [
                    'stalled_after_minutes' => (int) config('queues.scheduler.stalled_after_minutes', 15),
                    'failed_prune_after_hours' => (int) config('queues.failed.prune_after_hours', 168),
                ],
            ],
            'queues' => $queues,
            'channels' => $this->channels(),
            'totals' => [
                'queued' => $this->total($queues, 'queued'),
                'failed' => $this->total($queues, 'failed'),
            ],
        ]);
    }

    /**
     * Do not present an unreadable queue count as an empty queue.
     *
     * @param  list<array<string, mixed>>  $queues
     */
    protected function total(array $queues, string $key): ?int
    {
        $values = array_column($queues, $key);

        if (in_array(null, $values, true)) {
            return null;
        }

        return array_sum($values);
    }

    /**
     * Present a queue with the number of jobs waiting on it.
     *
     * @param  array{name: string, purpose: string, channels: list<string>, supervisor: string|null, cps: int, policy: array{tries: int, backoff: list<int>, timeout: int}}  $queue
     * @return array<string, mixed>
     */
    protected function row(array $queue): array
    {
        return [
            'name' => $queue['name'],
            'purpose' => $queue['purpose'],
            'channels' => $queue['channels'],
            'supervisor' => $queue['supervisor'],
            'cps' => $queue['cps'],
            'tries' => $queue['policy']['tries'],
            'backoff' => implode(', ', array_map(
                fn (int $seconds): string => $this->duration($seconds),
                $queue['policy']['backoff'],
            )),
            'timeout' => $this->duration($queue['policy']['timeout']),
            'queued' => $this->queued($queue['name']),
            'failed' => $this->failed($queue['name']),
        ];
    }

    /**
     * The per-channel throughput ceilings the dispatcher enforces.
     *
     * @return list<array{value: string, label: string, queue: string, cps: int}>
     */
    protected function channels(): array
    {
        return array_map(fn (Channel $channel): array => [
            'value' => $channel->value,
            'label' => $channel->label(),
            'queue' => $this->queues->queueForChannel($channel),
            'cps' => $this->pacer->perSecond($channel),
        ], [Channel::Sms, Channel::Voice, Channel::Email]);
    }

    /**
     * The number of jobs waiting on a queue, or null when the backend cannot be read.
     */
    protected function queued(string $name): ?int
    {
        try {
            return (int) Queue::size($name);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * The number of jobs that failed on a queue and are waiting on a decision.
     */
    protected function failed(string $name): ?int
    {
        try {
            return (int) DB::table('failed_jobs')->where('queue', $name)->count();
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * Describe a number of seconds the way an operator reads it.
     */
    protected function duration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s';
        }

        if ($seconds < 3600) {
            return round($seconds / 60, $seconds % 60 === 0 ? 0 : 1).'m';
        }

        return round($seconds / 3600, $seconds % 3600 === 0 ? 0 : 1).'h';
    }
}
