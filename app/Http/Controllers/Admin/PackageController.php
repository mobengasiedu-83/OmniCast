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
 * The wallet top up catalogue: which packages the platform sells and their public details.
 *
 * Configuration defines the defaults; package edits and availability changes
 * are stored as platform settings so they survive a deployment.
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

    /**
     * Update a package's public details and, optionally, its channel allocations.
     */
    public function updateDetails(Request $request, string $package): RedirectResponse
    {
        Gate::authorize(Permission::ManageWallet->value);

        abort_unless($this->packages->has($package), 404);

        $maximumPrice = $this->packages->limits()['max'];

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'price' => [
                'required',
                'numeric',
                'decimal:0,2',
                'min:0.01',
                'max:'.$maximumPrice,
            ],
            'popular' => ['required', 'boolean'],
            'tagline' => ['required', 'string', 'max:255'],
            'units' => ['sometimes', 'array:sms,voice,email', 'required_array_keys:sms,voice,email'],
            'units.sms' => ['required_with:units', 'integer', 'min:0', 'max:1000000000'],
            'units.voice' => ['required_with:units', 'integer', 'min:0', 'max:1000000000'],
            'units.email' => ['required_with:units', 'integer', 'min:0', 'max:1000000000'],
        ]);

        $details = [
            'name' => $validated['name'],
            'price' => round((float) $validated['price'], 2),
            'popular' => $request->boolean('popular'),
            'tagline' => $validated['tagline'],
        ];

        if (array_key_exists('units', $validated)) {
            $details['units'] = array_map('intval', $validated['units']);
        }

        $this->packages->setDetails($package, $details, $request->user());
        $this->recordActivity('updated wallet package', properties: [
            'package' => $package,
            'name' => $details['name'],
            'price' => $details['price'],
            'popular' => $details['popular'],
            'allocations_changed' => array_key_exists('units', $details),
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $details['name'].' package was updated.',
        ]);
    }
}
