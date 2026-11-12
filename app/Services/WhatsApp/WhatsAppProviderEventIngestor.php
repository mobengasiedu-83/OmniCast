<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppProvider;
use App\Models\WhatsAppProviderEvent;
use App\Models\WhatsAppUsageRecord;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WhatsAppProviderEventIngestor
{
    /** @param array<string, mixed> $payload */
    public function ingest(WhatsAppProvider $provider, array $payload, bool $signatureVerified): WhatsAppProviderEvent
    {
        if (! $signatureVerified) {
            throw new \LogicException('Provider events must pass signature verification before ingestion.');
        }
        validator($payload, [
            'event_id' => ['required', 'string', 'max:191'],
            'message_id' => ['required', 'string', 'max:191'],
            'event_type' => ['required', 'string', 'max:48'],
            'status' => ['required', 'string', 'max:24'],
            'occurred_at' => ['nullable', 'date'],
            'billable' => ['sometimes', 'boolean'],
            'usage_id' => ['nullable', 'string', 'max:191'],
            'category' => ['nullable', 'string', 'max:24'],
            'market' => ['nullable', 'string', 'size:2', 'alpha', 'uppercase'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3', 'alpha', 'uppercase'],
        ])->validate();

        $mappedStatus = $this->mapStatus(strtolower((string) $payload['status']));
        $safePayload = array_intersect_key($payload, array_flip(['event_id', 'message_id', 'event_type', 'status', 'occurred_at', 'billable', 'usage_id', 'category', 'market', 'quantity', 'amount', 'currency']));

        try {
            return DB::transaction(function () use ($provider, $payload, $signatureVerified, $mappedStatus, $safePayload): WhatsAppProviderEvent {
                WhatsAppProvider::query()->lockForUpdate()->findOrFail($provider->id);
                $existing = WhatsAppProviderEvent::query()->where('provider_id', $provider->id)->where('provider_event_id', $payload['event_id'])->first();
                if ($existing !== null) {
                    return $existing;
                }
                $recipient = WhatsAppCampaignRecipient::query()->where('provider_message_id', $payload['message_id'])
                    ->whereHas('campaign.connector', fn ($query) => $query->where('provider_id', $provider->id))
                    ->lockForUpdate()->first();
                $event = WhatsAppProviderEvent::query()->create([
                    'provider_id' => $provider->id,
                    'recipient_id' => $recipient?->id,
                    'provider_event_id' => $payload['event_id'],
                    'provider_message_id' => $payload['message_id'],
                    'event_type' => $payload['event_type'],
                    'status' => $mappedStatus,
                    'occurred_at' => isset($payload['occurred_at']) ? Carbon::parse($payload['occurred_at']) : now(),
                    'signature_verified' => $signatureVerified,
                    'billable' => $mappedStatus === 'delivered' && (bool) ($payload['billable'] ?? false),
                    'payload_redacted' => $safePayload,
                    'processing_status' => $recipient === null ? 'unmatched' : 'received',
                ]);
                if ($recipient === null) {
                    return $event;
                }

                if ($this->rank($mappedStatus) > $this->rank((string) $recipient->status)) {
                    $timestamps = match ($mappedStatus) {
                        'sent' => ['submitted_at' => $event->occurred_at],
                        'delivered' => ['delivered_at' => $event->occurred_at],
                        'read' => ['delivered_at' => $recipient->delivered_at ?? $event->occurred_at, 'read_at' => $event->occurred_at],
                        'failed' => ['failed_at' => $event->occurred_at],
                        'accepted' => ['accepted_at' => $event->occurred_at],
                        default => [],
                    };
                    $recipient->forceFill(['status' => $mappedStatus, ...$timestamps])->save();
                }

                if ($mappedStatus === 'delivered' && $event->billable && $recipient->unit_price !== null) {
                    $existingUsage = WhatsAppUsageRecord::query()->where('provider_id', $provider->id)->where('provider_message_id', $payload['message_id'])->exists();
                    if (! $existingUsage) {
                        $settlementReference = "wa-event-{$event->id}";
                        app(WhatsAppWalletService::class)->settleDelivered(
                            $recipient->campaign,
                            $settlementReference,
                            (float) $recipient->unit_price,
                            ['verified_billable_delivery' => true, 'provider_event_id' => $event->id, 'provider_id' => $provider->id],
                        );
                        WhatsAppUsageRecord::query()->create([
                            'provider_id' => $provider->id,
                            'recipient_id' => $recipient->id,
                            'provider_event_id' => $event->id,
                            'provider_usage_id' => $safePayload['usage_id'] ?? null,
                            'provider_message_id' => $payload['message_id'],
                            'category' => $recipient->unit_price_snapshot['category'] ?? $safePayload['category'] ?? null,
                            'market' => $recipient->unit_price_snapshot['market'] ?? $safePayload['market'] ?? null,
                            'quantity' => (int) ($safePayload['quantity'] ?? 1),
                            'amount' => $recipient->unit_price,
                            'internal_cost' => $this->internalCost($recipient->unit_price_snapshot),
                            'currency' => 'GHS',
                            'used_at' => $event->occurred_at,
                            'evidence' => ['provider_event_id' => $event->id, 'rate_snapshot' => $recipient->unit_price_snapshot, 'provider_category' => $safePayload['category'] ?? null, 'provider_market' => $safePayload['market'] ?? null],
                        ]);
                    }
                }

                $event->forceFill(['processing_status' => 'processed', 'processed_at' => now()])->save();
                if (in_array($mappedStatus, ['delivered', 'read', 'failed'], true)) {
                    app(WhatsAppWalletService::class)->releaseIfNoBillableOutcomes($recipient->campaign, 'Provider confirmed a terminal message outcome.');
                }

                return $event;
            });
        } catch (QueryException $exception) {
            $existing = WhatsAppProviderEvent::query()->where('provider_id', $provider->id)->where('provider_event_id', $payload['event_id'])->first();
            if ($existing !== null) {
                return $existing;
            }

            throw $exception;
        }
    }

    private function mapStatus(string $status): string
    {
        $mapped = match ($status) {
            'accepted', 'queued' => 'accepted',
            'sent', 'submitted' => 'sent',
            'delivered' => 'delivered',
            'read' => 'read',
            'failed', 'rejected' => 'failed',
            default => 'unknown',
        };
        if ($mapped === 'unknown' && ! in_array($status, ['unknown', 'pending'], true)) {
            throw ValidationException::withMessages(['status' => 'The provider event status is not supported by the configured event adapter.']);
        }

        return $mapped;
    }

    private function rank(string $status): int
    {
        return match ($status) {
            'accepted' => 1,
            'sent' => 2,
            'failed' => 3,
            'delivered' => 4,
            'read' => 5,
            default => 0,
        };
    }

    /** @param array<string, mixed>|null $snapshot */
    private function internalCost(?array $snapshot): ?float
    {
        if ($snapshot === null || ! isset($snapshot['meta_rate'], $snapshot['fx_rate'], $snapshot['provider_fee'], $snapshot['direct_cost'])) {
            return null;
        }

        return round(((float) $snapshot['meta_rate'] * (float) $snapshot['fx_rate']) + (float) $snapshot['provider_fee'] + (float) $snapshot['direct_cost'], 4);
    }
}
