<?php

namespace App\Services\Operations;

use App\Services\Delivery\QueueTopology;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use RuntimeException;
use Throwable;

class OperationalHealth
{
    public function __construct(protected QueueTopology $queues) {}

    /**
     * Run non-destructive checks for the services needed to operate the app.
     *
     * @return array{status: string, checked_at: string, checks: list<array{key: string, label: string, status: string, message: string, metrics: array<string, mixed>|null}>}
     */
    public function report(): array
    {
        $checks = [
            $this->check('database', 'Database', function (): array {
                DB::connection()->select('select 1');

                return ['message' => 'Database connection is responding.', 'metrics' => null];
            }),
            $this->check('cache', 'Cache', function (): array {
                $cache = Cache::store();
                $key = 'operations-health:'.Str::uuid();

                try {
                    $cache->put($key, 'ok', now()->addSeconds(30));

                    if ($cache->get($key) !== 'ok') {
                        throw new RuntimeException('Cache read-back did not match.');
                    }
                } finally {
                    $cache->forget($key);
                }

                return ['message' => 'Cache read/write check passed.', 'metrics' => null];
            }),
            $this->check('storage', 'Private storage', function (): array {
                $disk = Storage::disk('local');
                $path = 'health-checks/'.Str::uuid().'.tmp';

                try {
                    if (! $disk->put($path, 'ok') || $disk->get($path) !== 'ok') {
                        throw new RuntimeException('Private storage read/write check failed.');
                    }
                } finally {
                    $disk->delete($path);
                }

                return ['message' => 'Private storage read/write check passed.', 'metrics' => null];
            }),
            $this->check('queue', 'Queue backend', function (): array {
                $depths = [];

                foreach ($this->queues->queues() as $queue) {
                    $depths[$queue['name']] = (int) Queue::connection()->size($queue['name']);
                }

                return [
                    'message' => count($depths).' queue depths are readable.',
                    'metrics' => $depths,
                ];
            }),
            $this->horizonCheck(),
        ];

        return [
            'status' => collect($checks)->contains(fn (array $check): bool => $check['status'] === 'failed')
                ? 'unavailable'
                : 'ok',
            'checked_at' => now()->toIso8601String(),
            'checks' => $checks,
        ];
    }

    /**
     * Verify the Horizon master is reporting current Redis-backed supervisors.
     *
     * @return array{key: string, label: string, status: string, message: string, metrics: array<string, mixed>|null}
     */
    protected function horizonCheck(): array
    {
        $connection = (string) config('queue.default');
        $driver = (string) config('queue.connections.'.$connection.'.driver', $connection);

        if ($driver !== 'redis') {
            return [
                'key' => 'horizon',
                'label' => 'Horizon',
                'status' => 'not_applicable',
                'message' => 'Horizon is used with Redis-backed queues.',
                'metrics' => null,
            ];
        }

        return $this->check('horizon', 'Horizon', function (): array {
            $masters = app(MasterSupervisorRepository::class)->all();
            $runningMasters = array_filter(
                $masters,
                fn (object $master): bool => ($master->status ?? null) === 'running',
            );

            if ($runningMasters === []) {
                throw new RuntimeException('No running Horizon master is reporting.');
            }

            $supervisors = collect($runningMasters)
                ->flatMap(fn (object $master): array => is_array($master->supervisors ?? null) ? $master->supervisors : [])
                ->unique()
                ->values()
                ->all();

            return [
                'message' => count($runningMasters).' Horizon master(s) are running.',
                'metrics' => [
                    'masters' => count($runningMasters),
                    'supervisors' => $supervisors,
                ],
            ];
        });
    }

    /**
     * Turn dependency errors into a safe, explicit health result.
     *
     * @param  Closure(): array{message: string, metrics: array<string, mixed>|null}  $probe
     * @return array{key: string, label: string, status: string, message: string, metrics: array<string, mixed>|null}
     */
    protected function check(string $key, string $label, Closure $probe): array
    {
        try {
            $result = $probe();

            return [
                'key' => $key,
                'label' => $label,
                'status' => 'ok',
                'message' => $result['message'],
                'metrics' => $result['metrics'],
            ];
        } catch (Throwable $exception) {
            Log::warning('Operational health check failed.', [
                'check' => $key,
                'exception' => $exception::class,
            ]);

            return [
                'key' => $key,
                'label' => $label,
                'status' => 'failed',
                'message' => 'Unable to verify this service. Check application logs.',
                'metrics' => null,
            ];
        }
    }
}
