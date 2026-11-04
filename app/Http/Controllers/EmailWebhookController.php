<?php

namespace App\Http\Controllers;

use App\Http\Requests\Email\EmailDeliveryRequest;
use App\Services\EmailTracker;
use Illuminate\Http\JsonResponse;

/**
 * Receives the delivery, bounce and complaint receipts the mail server posts back.
 *
 * The reply is a plain 202 even when the reference is unknown: a provider retries
 * a receipt it could not deliver, and replaying a reference this platform never
 * submitted would never succeed.
 */
class EmailWebhookController extends Controller
{
    /**
     * Apply one signed receipt to its message.
     */
    public function __invoke(EmailDeliveryRequest $request, EmailTracker $tracker): JsonResponse
    {
        $message = $tracker->apply($request->payload());

        return response()->json([
            'status' => $message === null ? 'ignored' : 'accepted',
            'reference' => (string) $request->input('reference'),
        ], 202);
    }
}
