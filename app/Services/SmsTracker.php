<?php

namespace App\Services;

use App\Enums\CampaignContactStatus;
use App\Enums\CampaignStatus;
use App\Enums\SmsEncoding;
use App\Enums\SmsMessageStatus;
use App\Jobs\DispatchCampaignJob;
use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\SmsMessage;
use App\Services\Delivery\QueueTopology;
use App\Services\Sms\SmsDeliveryReport;
use App\Services\Sms\SmsDispatchResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Keeps the message log in step with what the network reports.
 *
 * Every send attempt writes an `sms_messages` row before the provider is
 * contacted, and every receipt appends to its `sms_message_events`, so the
 * delivery report is rebuilt from what the network confirmed instead of what
 * the dispatcher hoped for. A message the network could not deliver puts its
 * recipient back in the queue until the campaign runs out of retries.
 */
class SmsTracker
{
    public function __construct(protected QueueTopology $queues) {}

    /**
     * Write the message row before the provider is asked to deliver it.
     */
    public function start(
        Campaign $campaign,
        CampaignContact $recipient,
        string $provider,
        string $from,
        string $to,
        string $body,
        SmsEncoding $encoding,
        int $segments,
        float $cost = 0.0,
    ): SmsMessage {
        return SmsMessage::query()->create([
            'reference' => (string) Str::ulid(),
            'client_id' => $campaign->client_id,
            'campaign_id' => $campaign->id,
            'campaign_contact_id' => $recipient->id,
            'provider' => $provider,
            'to_number' => $to,
            'from_number' => $from,
            'body' => $body,
            'encoding' => $encoding,
            'segments' => max($segments, 1),
            'status' => SmsMessageStatus::Queued,
            'attempts' => $recipient->smsMessages()->count() + 1,
            'cost' => $cost,
        ]);
    }

    /**
     * Record what the provider answered to the submission.
     */
    public function submitted(SmsMessage $message, SmsDispatchResult $result): void
    {
        $message->forceFill([
            'provider_message_id' => $result->providerMessageId ?: $message->provider_message_id,
            'status' => $result->accepted ? SmsMessageStatus::Submitted : SmsMessageStatus::Failed,
            'submitted_at' => $result->accepted ? now() : null,
            'failed_at' => $result->accepted ? null : now(),
            'error_message' => $result->accepted ? null : $result->message,
        ])->save();

        if ($result->accepted) {
            return;
        }

        Log::channel('sms')->warning('SMS was rejected by the transport.', [
            'reference' => $message->reference,
            'provider' => $message->provider,
            'reason' => $result->message,
        ]);
    }

    /**
     * Apply one delivery receipt to the message it belongs to.
     *
     * Receipts the network retries are de-duplicated by provider event id, and a
     * reference that is not ours is ignored rather than trusted.
     */
    public function apply(SmsDeliveryReport $report): ?SmsMessage
    {
        $message = SmsMessage::query()->where('reference', $report->reference)->first();

        if ($message === null) {
            return null;
        }

        if ($report->providerEventId !== null
            && $message->events()->where('provider_event_id', $report->providerEventId)->exists()) {
            return $message;
        }

        $message->events()->create([
            'type' => $report->status->eventType(),
            'provider_status' => $report->providerStatus,
            'provider_event_id' => $report->providerEventId,
            'error_message' => $report->error,
            'occurred_at' => $report->occurredAt ?? now(),
            'payload' => $report->payload,
        ]);

        $this->advance($message, $report);

        return $message->refresh();
    }

    /**
     * Move the message forward, never backwards.
     */
    protected function advance(SmsMessage $message, SmsDeliveryReport $report): void
    {
        $now = $report->occurredAt ?? now();

        // A late receipt never rewrites a message that already reached a final
        // state; the first terminal receipt is the one the network stands by.
        if ($message->status->isTerminal()) {
            return;
        }

        $message->forceFill([
            'status' => $report->status,
            'provider_status' => $report->providerStatus ?? $message->provider_status,
            'provider_message_id' => $report->providerMessageId ?? $message->provider_message_id,
            'submitted_at' => $message->submitted_at ?? $now,
            'delivered_at' => $report->status->wasDelivered() ? ($message->delivered_at ?? $now) : $message->delivered_at,
            'failed_at' => $report->status->isRetryable() ? ($message->failed_at ?? $now) : $message->failed_at,
            'error_message' => $report->status->wasDelivered()
                ? null
                : ($report->error ?? 'The network could not deliver the message.'),
            'last_event' => [
                'receipt' => $report->status->value,
                'provider_status' => $report->providerStatus,
            ],
        ])->save();

        if ($report->status->wasDelivered()) {
            $this->confirmRecipient($message);

            return;
        }

        if ($report->status->isRetryable()) {
            $this->settle($message->refresh());
        }
    }

    /**
     * Promote the recipient once the handset confirmed the message.
     */
    protected function confirmRecipient(SmsMessage $message): void
    {
        $message->loadMissing('campaignContact');
        $recipient = $message->campaignContact;

        if ($recipient === null) {
            return;
        }

        $recipient->forceFill([
            'status' => CampaignContactStatus::Delivered,
            'delivered_at' => $recipient->delivered_at ?? $message->delivered_at ?? now(),
            'failed_at' => null,
            'error_message' => null,
        ])->save();
    }

    /**
     * Put the recipient back in the queue when the network could not deliver.
     */
    protected function settle(SmsMessage $message): void
    {
        $message->loadMissing(['campaign', 'campaignContact']);
        $campaign = $message->campaign;
        $recipient = $message->campaignContact;

        if ($campaign === null || $recipient === null) {
            return;
        }

        $attempts = max($recipient->attempts, 1);
        $exhausted = $attempts >= max($campaign->max_retries, 1);

        $recipient->forceFill([
            'status' => $exhausted ? CampaignContactStatus::Failed : CampaignContactStatus::Queued,
            'failed_at' => $exhausted ? now() : null,
            'error_message' => $exhausted
                ? "The message could not be delivered after {$attempts} attempts."
                : 'Waiting to resend after the network could not deliver the message.',
        ])->save();

        if ($exhausted) {
            return;
        }

        // The dispatcher closes a campaign as soon as it has submitted everyone,
        // so an undelivered message has to reopen it before the resend is queued.
        if ($campaign->status === CampaignStatus::Completed) {
            $campaign->forceFill([
                'status' => CampaignStatus::Running,
                'completed_at' => null,
            ])->save();
        }

        DispatchCampaignJob::dispatch($campaign->id)
            ->onQueue($this->queues->queueForCampaign($campaign))
            ->delay(now()->addMinutes(max($campaign->retry_delay_minutes, 1)));
    }
}
