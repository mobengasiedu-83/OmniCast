<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Operations\OperationalHealth;
use Illuminate\Http\JsonResponse;

/**
 * Says which API version answers, and whether the deployment is up.
 *
 * A monitoring probe points here: it needs no token, and the version in the
 * payload is the one the gateway is actually serving rather than the one in the
 * client's configuration.
 */
class HealthController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'version' => (string) config('api.version', 'v1'),
            'time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Report whether critical application dependencies are ready.
     */
    public function ready(OperationalHealth $health): JsonResponse
    {
        $report = $health->report();

        return response()->json([
            'status' => $report['status'],
            'checked_at' => $report['checked_at'],
            'checks' => array_map(
                fn (array $check): array => [
                    'name' => $check['key'],
                    'status' => $check['status'],
                ],
                $report['checks'],
            ),
        ], $report['status'] === 'ok' ? 200 : 503);
    }
}
