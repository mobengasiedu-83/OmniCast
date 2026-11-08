<?php

namespace App\Services\Automation;

use App\Models\AutomationRun;
use App\Models\AutomationStep;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Posts the payload of a webhook step to the URL the workflow owns.
 *
 * The call is signed exactly like a subscription delivery — an HMAC-SHA256 over
 * `<unix>.<raw body>` with the step secret — so a receiver can verify the call
 * with the same code it already uses for callbacks.
 */
class AutomationWebhookCaller
{
    /**
     * Deliver the step payload, reporting what the receiver answered.
     *
     * @return array{accepted: bool, detail: string, payload: array<string, mixed>}
     */
    public function send(AutomationRun $run, AutomationStep $step): array
    {
        $url = $step->value('url');
        $secret = $step->value('secret');

        if ($url === null || ! str_starts_with($url, 'https://')) {
            return [
                'accepted' => false,
                'detail' => 'The webhook step has no https URL to post to.',
                'payload' => [],
            ];
        }

        if ($secret === null) {
            return [
                'accepted' => false,
                'detail' => 'The webhook step has no signing secret.',
                'payload' => [],
            ];
        }

        $body = json_encode($this->payload($run, $step), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        $timestamp = now()->getTimestamp();

        try {
            $response = Http::connectTimeout((int) config('automations.webhook.connect_timeout', 5))
                ->timeout((int) config('automations.webhook.timeout', 10))
                ->withHeaders([
                    (string) config('automations.webhook.signature_header', 'X-Omnicast-Automation') => sprintf(
                        't=%d,v1=%s',
                        $timestamp,
                        hash_hmac('sha256', $timestamp.'.'.$body, $secret),
                    ),
                    'Accept' => 'application/json',
                ])
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (ConnectionException $exception) {
            Log::warning('An automation webhook step could not reach its receiver.', [
                'run_id' => $run->id,
                'step' => $step->position,
                'exception' => $exception::class,
            ]);

            return [
                'accepted' => false,
                'detail' => 'The receiver could not be reached.',
                'payload' => ['url' => $url],
            ];
        }

        return [
            'accepted' => $response->successful(),
            'detail' => $response->successful()
                ? 'The receiver answered '.$response->status().'.'
                : 'The receiver answered '.$response->status().'.',
            'payload' => ['url' => $url, 'status' => $response->status()],
        ];
    }

    /**
     * The signed payload a webhook step posts.
     *
     * @return array<string, mixed>
     */
    protected function payload(AutomationRun $run, AutomationStep $step): array
    {
        $contact = $run->contact;
        $workflow = $run->workflow;

        return [
            'event' => 'automation.step',
            'sent_at' => now()->toIso8601String(),
            'workflow' => [
                'id' => $workflow?->id,
                'name' => $workflow?->name,
                'trigger' => $workflow?->trigger->value,
            ],
            'run' => [
                'id' => $run->id,
                'position' => $step->position,
                'status' => $run->status->value,
            ],
            'contact' => $contact === null ? null : [
                'id' => $contact->id,
                'name' => $contact->full_name,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'dnd' => (bool) $contact->dnd,
            ],
            'campaign_id' => $run->campaign_id,
            'context' => $run->context ?? [],
        ];
    }
}
