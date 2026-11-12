<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppProvider;
use App\Services\WhatsApp\WhatsAppProviderEventIngestor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppProviderEventController extends Controller
{
    public function __invoke(Request $request, WhatsAppProviderEventIngestor $ingestor): JsonResponse
    {
        abort_unless(config('whatsapp.events.contract_enabled', false), 404);
        $secret = config('whatsapp.events.signing_secret');
        $providerId = config('whatsapp.events.provider_id');
        abort_unless(is_string($secret) && $secret !== '' && is_numeric($providerId), 503);
        $signature = (string) $request->header('X-WhatsApp-Signature', '');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
        abort_unless(hash_equals($expected, $signature), 401);

        $payload = $request->json()->all();
        $event = $ingestor->ingest(WhatsAppProvider::query()->findOrFail((int) $providerId), $payload, true);

        return response()->json(['status' => 'accepted', 'event_id' => $event->provider_event_id], 202);
    }
}
