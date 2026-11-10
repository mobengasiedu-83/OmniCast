<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Services\Operations\OperationalHealth;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SystemController extends Controller
{
    public function __construct(protected OperationalHealth $health) {}

    /**
     * Show application and dependency health to authorised platform operators.
     */
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::ViewQueues->value);

        return Inertia::render('admin/system/index', [
            'health' => $this->health->report(),
            'runtime' => [
                'environment' => app()->environment(),
                'php' => PHP_VERSION,
                'laravel' => Application::VERSION,
                'queue_connection' => (string) config('queue.default'),
                'queue_driver' => (string) config(
                    'queue.connections.'.config('queue.default').'.driver',
                    config('queue.default'),
                ),
                'cache_store' => (string) config('cache.default'),
                'maintenance' => app()->isDownForMaintenance(),
                'horizon_path' => '/'.ltrim((string) config('horizon.path', 'horizon'), '/'),
                'horizon_accessible' => $request->user()?->can('viewHorizon') ?? false,
                'support_accessible' => $request->user()?->can('support.view') ?? false,
            ],
        ]);
    }

    /**
     * Download the operator runbook to keep detailed recovery procedures staff-only.
     */
    public function runbook(): BinaryFileResponse
    {
        Gate::authorize(Permission::ViewQueues->value);

        return response()->download(
            base_path('docs/OPERATIONS-RUNBOOK.md'),
            'OmniCast-Operations-Runbook.md',
            ['Content-Type' => 'text/markdown'],
        );
    }
}
