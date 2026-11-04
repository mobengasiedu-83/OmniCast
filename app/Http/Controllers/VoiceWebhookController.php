<?php

namespace App\Http\Controllers;

use App\Http\Requests\Voice\VoiceCallbackRequest;
use App\Services\VoiceCallTracker;
use Illuminate\Http\JsonResponse;

/**
 * Receives the call events the PBX posts back to OmniCast.
 *
 * The reply is a plain 202 even when the reference is unknown: a switch retries
 * a callback it could not deliver, and replaying a reference this platform never
 * dialled would never succeed.
 */
class VoiceWebhookController extends Controller
{
    /**
     * Apply one signed call event to its call.
     */
    public function __invoke(VoiceCallbackRequest $request, VoiceCallTracker $tracker): JsonResponse
    {
        $call = $tracker->apply($request->payload());

        return response()->json([
            'status' => $call === null ? 'ignored' : 'accepted',
            'reference' => (string) $request->input('reference'),
        ], 202);
    }
}
