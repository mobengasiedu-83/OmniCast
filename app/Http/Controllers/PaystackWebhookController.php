<?php

namespace App\Http\Controllers;

use App\Http\Requests\Billing\PaystackWebhookRequest;
use App\Services\Billing\CheckoutService;
use Illuminate\Http\JsonResponse;

/**
 * Receives Paystack's charge events.
 *
 * The reply is a 200 even when the reference is unknown: Paystack retries a
 * webhook it did not get a 200 for, and replaying a reference this platform
 * never created would never succeed. A payload that fails the signature check
 * is rejected before it reaches the checkout service.
 */
class PaystackWebhookController extends Controller
{
    /**
     * Apply one signed charge event to its payment.
     */
    public function __invoke(PaystackWebhookRequest $request, CheckoutService $checkout): JsonResponse
    {
        $payment = $checkout->handleEvent($request->event(), $request->payload());

        return response()->json([
            'status' => $payment === null ? 'ignored' : $payment->status->value,
            'reference' => $payment?->reference,
        ]);
    }
}
