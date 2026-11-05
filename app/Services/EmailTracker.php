<?php

namespace App\Services;

use App\Enums\CampaignContactStatus;
use App\Enums\CampaignStatus;
use App\Enums\ContactSuppressionReason;
use App\Enums\EmailFormat;
use App\Enums\EmailMessageStatus;
use App\Jobs\DispatchCampaignJob;
use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\ContactSuppression;
use App\Models\EmailMessage;
use App\Services\Delivery\QueueTopology;
use App\Services\Email\EmailDeliveryReport;
use App\Services\Email\EmailDispatchResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Keeps the email log in step with what the mail server reports.
 *
 * Every send attempt writes an `email_messages` row before the transport is
 * contacted, and every receipt appends to its `email_message_events`, so the
 * delivery report is rebuilt from what the mail server confirmed instead of what
 * the dispatcher hoped for. A bounced message puts its recipient back in the
 * queue until the campaign runs out of retries, and a hard bounce or a spam
 * complaint also suppresses the address so it is never mailed again.
 */
class EmailTracker
{
    public function __construct(protected QueueTopology $queues) {}

    /**
     * Write the message row before the transport is asked to deliver it.
     */
    public function start(
        Campaign $campaign,
        CampaignContact $recipient,
        string $provider,
        string $from,
        ?string $fromName,
        string $to,
        string $subject,
        string $body,
        EmailFormat $format,
        float $cost = 0.0,
    ): EmailMessage {
        return EmailMessage::query()->create([
            'reference' => (string) Str::ulid(),
            'client_id' => $campaign->client_id,
            'campaign_id' => $campaign->id,
            'campaign_contact_id' => $recipient->id,
            'provider' => $provider,
            'to_address' => $to,
            'from_address' => $from,
            'from_name' => $fromName,
            'subject' => $subject,
            'body' => $body,
            'format' => $format,
            'status' => EmailMessageStatus::Queued,
            'attempts' => $recipient->emailMessages()->count() + 1,
            'cost' => $cost,
        ]);
    }

    /**
     * Record what the transport answered to the submission.
     */
    public function submitted(EmailMessage $message, EmailDispatchResult $result): void
    {
        $message->forceFill([
            'provider_message_id' => $result->providerMessageId ?: $message->provider_message_id,
            'status' => $result->accepted ? EmailMessageStatus::Submitted : EmailMessageStatus::Failed,
            'submitted_at' => $result->accepted ? now() : null,
            'failed_at' => $result->accepted ? null : now(),
            'error_message' => $result->accepted ? null : $result->message,
        ])->save();

        if ($result->accepted) {
            return;
        }

        Log::channel('email')->warning('Email was rejected by the transport.', [
            'reference' => $message->reference,
            'provider' => $message->provider,
            'reason' => $result->message,
        ]);
    }

    /**
     * Apply one delivery, bounce or complaint receipt to its message.
     *
     * Receipts the mail server retries are de-duplicated by provider event id, and
     * a reference that is not ours is ignored rather than trusted.
     */
    public function apply(EmailDeliveryReport $report): ?EmailMessage
    {
        $message = EmailMessage::query()->where('reference', $report->reference)->first();

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
    protected function advance(EmailMessage $message, EmailDeliveryReport $report): void
    {
        $now = $report->occurredAt ?? now();

        // A late receipt never rewrites a message that already reached a final
        // state; the first terminal receipt is the one the mail server stands by.
        if ($message->status->isTerminal()) {
            return;
        }

        $message->forceFill([
            'status' => $report->status,
            'provider_status' => $report->providerStatus ?? $message->provider_status,
            'provider_message_id' => $report->providerMessageId ?? $message->provider_message_id,
            'submitted_at' => $message->submitted_at ?? $now,
            'delivered_at' => $report->status->wasDelivered() ? ($message->delivered_at ?? $now) : $message->delivered_at,
            'bounced_at' => $report->status === EmailMessageStatus::Bounced ? ($message->bounced_at ?? $now) : $message->bounced_at,
            'failed_at' => $report->status->isRetryable() || $report->status === EmailMessageStatus::Complained
                ? ($message->failed_at ?? $now)
                : $message->failed_at,
            'error_message' => $report->status->wasDelivered()
                ? null
                : ($report->error ?? 'The mailbox could not receive the message.'),
            'last_event' => [
                'receipt' => $report->status->value,
                'provider_status' => $report->providerStatus,
            ],
        ])->save();

        if ($report->status->suppressesRecipient()) {
            $this->suppress($message, $report->status);
        }

        if ($report->status->wasDelivered()) {
            $this->confirmRecipient($message);

            return;
        }

        if ($report->status->isRetryable()) {
            $this->settle($message->refresh());
        }
    }

    /**
     * Promote the recipient once the mailbox confirmed the message.
     */
    protected function confirmRecipient(EmailMessage $message): void
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
     * Put the recipient back in the queue when the mailbox bounced the message.
     */
    protected function settle(EmailMessage $message): void
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
                : 'Waiting to resend after the mailbox bounced the message.',
        ])->save();

        if ($exhausted) {
            return;
        }

        // The dispatcher closes a campaign as soon as it has submitted everyone,
        // so a bounced message has to reopen it before the resend is queued.
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

    /**
     * Record a hard bounce or a spam complaint against the recipient address.
     *
     * The suppression registry is what stops a re-imported address from being
     * mailed again, so bounce and complaint feedback both land there.
     */
    protected function suppress(EmailMessage $message, EmailMessageStatus $status): void
    {
        $address = mb_strtolower(trim($message->to_address));

        if ($address === '') {
            return;
        }

        $exists = ContactSuppression::query()
            ->where('channel', 'email')
            ->where('identifier', $address)
            ->where('is_active', true)
            ->whereNull('lifted_at')
            ->where(fn ($query) => $query->whereNull('client_id')->orWhere('client_id', $message->client_id))
            ->exists();

        if ($exists) {
            return;
        }

        ContactSuppression::query()->create([
            'client_id' => $message->client_id,
            'channel' => 'email',
            'identifier' => $address,
            'reason' => $status === EmailMessageStatus::Complained
                ? ContactSuppressionReason::Complaint
                : ContactSuppressionReason::Bounce,
            'source' => 'provider feedback',
            'is_active' => true,
            'suppressed_at' => now(),
        ]);
    }
}
