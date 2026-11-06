<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\RecordsActivity;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Services\Billing\CreditEstimator;
use App\Services\Billing\PricingSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The dynamic price list: what a unit sells for, and whether clients may name
 * their own amount.
 *
 * The rates and limits ship in `config/billing.php`; this screen stores the
 * platform team's overrides as `billing.*` platform settings, which win over the
 * configuration everywhere a price is quoted — the public price list, the wallet,
 * the checkout and the mobile money form.
 *
 * The switch is the important part: with dynamic pricing off, the amount slider
 * leaves every screen and a submitted amount is refused at validation, so the
 * wallet only sells catalogue packages.
 */
class PricingController extends Controller
{
    use RecordsActivity;

    public function __construct(
        protected CreditEstimator $estimator,
        protected PricingSettings $settings,
    ) {}

    /**
     * Show the published price list with the switch and the overrides.
     */
    public function index(): Response
    {
        Gate::authorize(Permission::ManageWallet->value);

        $slider = $this->estimator->slider();

        return Inertia::render('admin/pricing/index', [
            'currency' => $this->estimator->currency(),
            'dynamic_pricing' => $this->estimator->dynamicPricingEnabled(),
            'rates' => $this->estimator->rates(),
            'limits' => $this->estimator->limits(),
            'slider' => $slider,
            'defaults' => $this->settings->defaults(),
            'overrides' => $this->settings->overrides(),
            'preview' => $this->estimator->estimate($slider['default']),
        ]);
    }

    /**
     * Store a new price list and the state of the slider.
     */
    public function update(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::ManageWallet->value);

        $validated = $request->validate([
            'dynamic_pricing' => ['required', 'boolean'],
            'rates' => ['required', 'array'],
            'rates.sms' => ['required', 'numeric', 'min:0.0001', 'max:10000'],
            'rates.voice' => ['required', 'numeric', 'min:0.0001', 'max:10000'],
            'rates.email' => ['required', 'numeric', 'min:0.0001', 'max:10000'],
            'limits' => ['required', 'array'],
            'limits.min' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'limits.max' => ['required', 'numeric', 'gt:limits.min'],
            'slider' => ['required', 'array'],
            'slider.min' => ['required', 'numeric', 'min:0'],
            'slider.max' => ['required', 'numeric', 'gt:slider.min'],
            'slider.step' => ['required', 'numeric', 'min:0.01'],
            'slider.default' => ['required', 'numeric', 'min:0'],
        ], [
            'rates.sms.min' => 'One SMS part has to cost more than nothing.',
            'rates.voice.min' => 'One voice minute has to cost more than nothing.',
            'rates.email.min' => 'One email has to cost more than nothing.',
            'limits.max.gt' => 'The largest top up has to be above the smallest.',
            'slider.max.gt' => 'The slider has to end above the amount it starts on.',
            'slider.step.min' => 'The slider has to move by at least 0.01.',
        ]);

        $dynamic = (bool) $validated['dynamic_pricing'];

        $this->settings->save([
            'dynamic_pricing' => $dynamic,
            'rates' => array_map(fn (mixed $rate): float => (float) $rate, $validated['rates']),
            'limits' => [
                'min' => (float) $validated['limits']['min'],
                'max' => (float) $validated['limits']['max'],
            ],
            'slider' => [
                'min' => (float) $validated['slider']['min'],
                'max' => (float) $validated['slider']['max'],
                'step' => (float) $validated['slider']['step'],
                'default' => (float) $validated['slider']['default'],
            ],
        ], $request->user());

        $this->recordActivity('updated the dynamic price list', properties: [
            'dynamic_pricing' => $dynamic,
            'rates' => $validated['rates'],
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $dynamic
                ? 'The price list is saved and clients may choose their own amount.'
                : 'The price list is saved. The amount slider is switched off.',
        ]);
    }
}
