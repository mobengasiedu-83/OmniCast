<?php

namespace App\Http\Controllers;

use App\Services\Billing\CreditEstimator;
use App\Services\Billing\PackageCatalogue;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public price list.
 *
 * It is the same catalogue the wallet checkout sells, published with the rates
 * the amount slider quotes from: a visitor sees exactly the packages, prices and
 * channel allocations a client buys from, and what any other amount would buy.
 */
class PricingController extends Controller
{
    public function __construct(
        protected PackageCatalogue $packages,
        protected CreditEstimator $estimator,
    ) {}

    /**
     * Show the published package price list.
     */
    public function __invoke(): Response
    {
        return Inertia::render('pricing', [
            'currency' => $this->packages->currency(),
            'packages' => $this->packages->all(),
            'rates' => $this->estimator->rates(),
            'slider' => $this->estimator->slider(),
            'dynamic_pricing' => $this->estimator->dynamicPricingEnabled(),
            'limits' => $this->packages->limits(),
        ]);
    }
}
