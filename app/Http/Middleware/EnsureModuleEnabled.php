<?php

namespace App\Http\Middleware;

use App\Enums\ClientService;
use App\Models\User;
use App\Services\ModuleAvailability;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stops a switched-off module being reached by its URL.
 *
 * Navigation never offers a module the platform or the tenant turned off, so
 * this closes the door on bookmarked and shared links to a screen that no
 * longer exists for the person holding it.
 */
class EnsureModuleEnabled
{
    public function __construct(private ModuleAvailability $modules) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        $service = ClientService::tryFrom($module);

        abort_if($service === null, 404);

        $user = $request->user();

        abort_unless(
            $this->modules->enabled($service, $user instanceof User ? $user->client : null),
            403,
            "The {$service->label()} module is not available.",
        );

        return $next($request);
    }
}
