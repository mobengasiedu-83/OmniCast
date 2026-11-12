<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppProvider;
use App\Services\WhatsApp\WhatsAppProviderEventIngestor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MetaWhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $verifyToken = (string) config('whatsapp.meta.verify_token', '');
        $mode = (string) $request->query->get('hub.mode', $request->query->get('hub_mode', ''));
        $providedToken = (string) $request->query->get('hub.verify_token', $request->query->get('hub_verify_token', ''));
        $challenge = (string) $request->query->get('hub.challenge', $request->query->get('hub_challenge', ''));
        abort_unless($verifyToken !== '' && $mode === 'subscribe' && hash_equals($verifyToken, $providedToken), 403);

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request, WhatsAppProviderEventIngestor $ingestor): JsonResponse
    {
        $secret = (string) config('whatsapp.meta.app_secret', '');
        abort_unless($secret !== '' && (bool) config('whatsapp.meta.onboarding_enabled'), 404);
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
        abort_unless(hash_equals($expected, (string) $request->header('X-Hub-Signature-256', '')), 401);

        $provider = WhatsAppProvider::query()->where('driver', 'meta_cloud')->firstOrFail();
        $accepted = 0;
        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) data_get($entry, 'changes', []) as $change) {
                foreach ((array) data_get($change, 'value.statuses', []) as $status) {
                    if (! is_array($status) || ! isset($status['id'], $status['status'])) {
                        continue;
                    }
                    $timestamp = (string) ($status['timestamp'] ?? time());
                    $eventId = implode(':', ['meta', $status['id'], $status['status'], $timestamp]);
                    $ingestor->ingest($provider, [
                        'event_id' => substr($eventId, 0, 191),
                        'message_id' => (string) $status['id'],
                        'event_type' => (string) $status['status'],
                        'status' => (string) $status['status'],
                        'occurred_at' => ctype_digit($timestamp) ? date(DATE_ATOM, (int) $timestamp) : now()->toISOString(),
                        'billable' => (bool) data_get($status, 'pricing.billable', false),
                        'category' => strtoupper((string) data_get($status, 'pricing.category', '')) ?: null,
                    ], true);
                    $accepted++;
                }
            }
        }

        return response()->json(['status' => 'accepted', 'events' => $accepted], 202);
    }
}
