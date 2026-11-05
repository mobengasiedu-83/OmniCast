<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\RecordsActivity;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Services\Billing\PackageCatalogue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The wallet top up catalogue: which packages the platform is selling.
 *
 * Packages themselves are configured in `config/billing.php`; this screen only
 * puts one on sale or takes it off. The choice is stored as a platform setting
 * so it survives a deployment without touching the configuration.
 */
class PackageController extends Controller
{
    use RecordsActivity;

    public function __construct(protected PackageCatalogue $packages) {}

    /**
     * List every package with its price, allocation and availability.
     */
    public function index(): Response
    {
        Gate::authorize(Permission::ManageWallet->value);

        return Inertia::render('admin/packages/index', [
            'packages' => $this->packages->every(),
            'currency' => $this->packages->currency(),
            'limits' => $this->packages->limits(),
        ]);
    }

    /**
     * Put a package on sale, or hide it from the price list again.
     */
    public function update(Request $request, string $package): RedirectResponse
    {
        Gate::authorize(Permission::ManageWallet->value);

        abort_unless($this->packages->has($package), 404);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $enabled = (bool) $validated['enabled'];
        $name = $this->packages->find($package)['name'] ?? ucfirst($package);

        $this->packages->setEnabled($package, $enabled, $request->user());
        $this->recordActivity(
            $enabled ? 'enabled wallet package' : 'disabled wallet package',
            properties: ['package' => $package, 'enabled' => $enabled],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $name.' package '.($enabled ? 'is on sale.' : 'is hidden.'),
        ]);
    }
}
