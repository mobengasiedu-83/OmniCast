<?php

namespace App\Http\Controllers;

use App\Services\Billing\PackageCatalogue;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public price list.
 *
 * It is the same catalogue the wallet checkout sells, so a visitor sees exactly
 * the packages, prices and channel allocations a client buys from.
 */
class PricingController extends Controller
{
    public function __construct(protected PackageCatalogue $packages) {}

    /**
     * Show the published package price list.
     */
    public function __invoke(): Response
    {
        return Inertia::render('pricing', [
            'currency' => $this->packages->currency(),
            'packages' => $this->packages->all(),
            'limits' => $this->packages->limits(),
        ]);
    }
}
