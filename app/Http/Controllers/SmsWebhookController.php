<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sms\SmsDeliveryRequest;
use App\Services\SmsTracker;
use Illuminate\Http\JsonResponse;

/**
 * Receives the delivery receipts the network posts back to OmniCast.
 *
 * The reply is a plain 202 even when the reference is unknown: a provider
 * retries a receipt it could not deliver, and replaying a reference this
 * platform never submitted would never succeed.
 */
class SmsWebhookController extends Controller
{
    /**
     * Apply one signed delivery receipt to its message.
     */
    public function __invoke(SmsDeliveryRequest $request, SmsTracker $tracker): JsonResponse
    {
        $message = $tracker->apply($request->payload());

        return response()->json([
            'status' => $message === null ? 'ignored' : 'accepted',
            'reference' => (string) $request->input('reference'),
        ], 202);
    }
}
