<?php

namespace App\Services;

use App\Contracts\EmailGateway;
use App\Contracts\SmsGateway;
use App\Contracts\VoiceGateway;
use App\Enums\CampaignContactStatus;
use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Enums\EmailFormat;
use App\Enums\ReservationStatus;
use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\Contact;
use App\Services\Delivery\DeliveryPacer;
use App\Services\Email\OutboundEmail;
use App\Services\Sms\OutboundSms;
use App\Services\Voice\VoiceCall as VoiceCallData;
use Illuminate\Support\Collection;

/**
 * Sends a campaign to its audience, bills the client and closes the campaign out.
 */
class CampaignDispatcher
{
    public function __construct(
        protected SmsGateway $sms,
        protected VoiceGateway $voice,
        protected EmailGateway $email,
        protected CampaignCostEstimator $costs,
        protected SmsSegmenter $segmenter,
        protected WalletService $wallet,
        protected PlatformConfiguration $configuration,
        protected ContactEligibility $eligibility,
        protected CampaignReadiness $readiness,
        protected VoiceIvr $ivr,
        protected VoiceCallTracker $tracker,
        protected SmsTracker $smsTracker,
        protected EmailTracker $emailTracker,
        protected DeliveryPacer $pacer,
    ) {}

    /**
     * Send one pass over the campaign audience.
     *
     * @return bool whether recipients are still waiting for a retry
     */
    public function run(Campaign $campaign): bool
    {
        $campaign->loadMissing('client');
        $disabledChannels = array_filter(
            $campaign->channel->deliveryChannels(),
            fn (Channel $channel): bool => ! $this->configuration->channelEnabled($channel->value, $campaign->client),
        );
        if ($disabledChannels !== []) {
            $campaign->campaignContacts()
                ->whereIn('status', [CampaignContactStatus::Pending->value, CampaignContactStatus::Queued->value])
                ->update([
                    'status' => CampaignContactStatus::Failed->value,
                    'failed_at' => now(),
                    'error_message' => 'Delivery was disabled by platform settings.',
                ]);
            $spend = (float) $campaign->campaignContacts()->sum('cost');
            $this->finalize($campaign, $spend);

            return false;
        }

        $campaign->forceFill([
            'status' => CampaignStatus::Running,
            'launched_at' => $campaign->launched_at ?? now(),
        ])->save();

        $campaign->loadMissing(['senderId', 'callerId', 'client']);

        $blockers = $this->readiness->issues($campaign);

        if ($blockers !== []) {
            $campaign->campaignContacts()
                ->whereIn('status', [CampaignContactStatus::Pending->value, CampaignContactStatus::Queued->value])
                ->update([
                    'status' => CampaignContactStatus::Failed->value,
                    'failed_at' => now(),
                    'error_message' => 'Delivery blocked: '.$blockers[0]['label'].'.',
                ]);
            $this->finalize($campaign, (float) $campaign->campaignContacts()->sum('cost'));

            return false;
        }

        $smsPartCost = $this->costs->smsPartCost($campaign->client);
        $voiceCallCost = $this->costs->voiceCallCost($campaign->client);
        $emailRecipientCost = $this->costs->emailRecipientCost($campaign->client);
        $smsParts = $campaign->channel->requiresSmsContent()
            ? max($this->segmenter->segmentsFor((string) $campaign->sms_message), 1)
            : 0;

        $smsCostPerRecipient = round($smsParts * $smsPartCost, 4);
        $costPerRecipient = round(
            $smsCostPerRecipient
            + ($campaign->channel->requiresVoiceContent() ? $voiceCallCost : 0)
            + ($campaign->channel->requiresEmailContent() ? $emailRecipientCost : 0),
            4,
        );

        $spend = (float) $campaign->campaignContacts()->sum('cost');

        $campaign->campaignContacts()
            ->with('contact')
            ->whereIn('status', [CampaignContactStatus::Pending->value, CampaignContactStatus::Queued->value])
            ->chunkById(200, function (Collection $rows) use ($campaign, $costPerRecipient, $smsCostPerRecipient, $emailRecipientCost, &$spend): void {
                foreach ($rows as $row) {
                    $this->dispatchTo($campaign, $row, $costPerRecipient, $smsCostPerRecipient, $emailRecipientCost, $spend);
                }
            });

        if ($this->awaitingRetry($campaign)) {
            return true;
        }

        $this->finalize($campaign, $spend);

        return false;
    }

    /**
     * Send the campaign to a single contact and record the outcome.
     */
    protected function dispatchTo(Campaign $campaign, CampaignContact $row, float $costPerRecipient, float $smsCostPerRecipient, float $emailCostPerRecipient, float &$spend): void
    {
        $contact = $row->contact;

        if (! $contact instanceof Contact) {
            $row->forceFill(['status' => CampaignContactStatus::Skipped])->save();

            return;
        }

        $channelDecisions = $this->eligibility->snapshot(
            $contact,
            array_map(fn (Channel $channel): string => $channel->value, $campaign->channel->deliveryChannels()),
        );
        $row->forceFill(['recipient_snapshot' => [
            'evaluated_at' => now()->toIso8601String(),
            'channels' => $channelDecisions,
        ]])->save();

        if (! collect($channelDecisions)->contains(fn (array $decision): bool => $decision['eligible'])) {
            $row->forceFill(['status' => CampaignContactStatus::Skipped])->save();

            return;
        }

        $delivered = true;
        $failure = 'The gateway rejected the message.';

        foreach ($campaign->channel->deliveryChannels() as $channel) {
            if (! ($channelDecisions[$channel->value]['eligible'] ?? false)) {
                continue;
            }

            $this->pacer->await($channel, $campaign->client);

            $delivered = match ($channel) {
                Channel::Sms => $this->sendSms($campaign, $row, $contact, $smsCostPerRecipient),
                Channel::Voice => $this->callVoice($campaign, $row, $contact, $costPerRecipient),
                Channel::Email => $this->sendEmail($campaign, $row, $contact, $emailCostPerRecipient),
                default => false,
            };

            if (! $delivered) {
                $failure = match ($channel) {
                    Channel::Voice => 'The switch did not accept the call.',
                    Channel::Email => 'The mail server rejected the message.',
                    default => 'The gateway rejected the message.',
                };
                break;
            }
        }

        $attempts = $row->attempts + 1;
        $now = now();

        if ($delivered) {
            $row->forceFill([
                'status' => CampaignContactStatus::Delivered,
                'attempts' => $attempts,
                'last_attempt_at' => $now,
                'sent_at' => $row->sent_at ?? $now,
                'delivered_at' => $now,
                'failed_at' => null,
                'error_message' => null,
                'cost' => $costPerRecipient,
            ])->save();

            $spend = round($spend + $costPerRecipient, 4);

            return;
        }

        $exhausted = $attempts >= max($campaign->max_retries, 1);

        $row->forceFill([
            'status' => $exhausted ? CampaignContactStatus::Failed : CampaignContactStatus::Queued,
            'attempts' => $attempts,
            'last_attempt_at' => $now,
            'failed_at' => $exhausted ? $now : null,
            'error_message' => $failure,
        ])->save();
    }

    /**
     * Log the message, then ask the provider to deliver it.
     *
     * The row is written before the submission so a receipt that arrives faster
     * than the provider response can still be matched to its message.
     */
    protected function sendSms(Campaign $campaign, CampaignContact $recipient, Contact $contact, float $cost): bool
    {
        $to = trim((string) $contact->phone);
        $from = trim((string) ($campaign->senderId?->name ?? 'OMNICAST'));

        if ($to === '' || $from === '') {
            return false;
        }

        $body = (string) $campaign->sms_message;

        $message = $this->smsTracker->start(
            $campaign,
            $recipient,
            $this->sms->name(),
            $from,
            $to,
            $body,
            $this->segmenter->encodingFor($body),
            $this->segmenter->segmentsFor($body),
            $cost,
        );

        $result = $this->sms->send(new OutboundSms(
            reference: $message->reference,
            to: $to,
            from: $from,
            body: $body,
            encoding: $message->encoding,
            segments: $message->segments,
        ));

        $this->smsTracker->submitted($message, $result);

        return $result->accepted;
    }

    /**
     * Log the email, then ask the transport to deliver it.
     *
     * The row is written before the submission so a bounce that arrives faster
     * than the transport response can still be matched to its message.
     */
    protected function sendEmail(Campaign $campaign, CampaignContact $recipient, Contact $contact, float $cost): bool
    {
        $to = trim((string) $contact->email);
        $from = trim((string) ($campaign->senderId?->name ?? config('email.from.address')));
        $fromName = trim((string) ($campaign->client?->name ?? config('email.from.name')));
        $subject = trim((string) $campaign->email_subject);
        $body = (string) $campaign->email_body;

        if ($to === '' || $from === '' || $body === '') {
            return false;
        }

        $message = $this->emailTracker->start(
            $campaign,
            $recipient,
            $this->email->name(),
            $from,
            $fromName === '' ? null : $fromName,
            $to,
            $subject,
            $body,
            $campaign->email_format ?? EmailFormat::Html,
            $cost,
        );

        $result = $this->email->send(new OutboundEmail(
            reference: $message->reference,
            to: $to,
            from: $from,
            subject: $subject,
            body: $body,
            format: $message->format,
            fromName: $message->from_name,
        ));

        $this->emailTracker->submitted($message, $result);

        return $result->accepted;
    }

    /**
     * Log the call, then ask the switch to dial it.
     *
     * The row is written before the originate action so a callback that arrives
     * faster than the originate response can still be matched to its call.
     */
    protected function callVoice(Campaign $campaign, CampaignContact $recipient, Contact $contact, float $cost): bool
    {
        $to = trim($contact->phone);
        $from = trim(
            $this->readiness->outboundCallerId($campaign->client)
                ?? $campaign->callerId?->number
                ?? (string) config('voice.outbound_caller_id')
        );

        if ($to === '' || $from === '') {
            return false;
        }

        $plan = $this->ivr->planFor($campaign);
        $call = $this->tracker->start($campaign, $recipient, $plan, $this->voice->name(), $from, $to, $cost);

        $result = $this->voice->dial(new VoiceCallData(
            reference: $call->reference,
            to: $to,
            from: $from,
            script: (string) $campaign->voice_script,
            recordingPath: $campaign->voice_recording_path,
            ivr: $plan,
            maxDurationSeconds: max((int) $campaign->voice_max_duration_seconds, 5),
        ));

        $this->tracker->dialled($call, $result);

        return $result->accepted;
    }

    /**
     * Determine whether any recipient is waiting for another attempt.
     */
    protected function awaitingRetry(Campaign $campaign): bool
    {
        return $campaign->campaignContacts()
            ->whereIn('status', [CampaignContactStatus::Pending->value, CampaignContactStatus::Queued->value])
            ->exists();
    }

    /**
     * Close the campaign, capture the real spend and release the unused reservation.
     */
    protected function finalize(Campaign $campaign, float $spend): void
    {
        $campaign->forceFill([
            'status' => CampaignStatus::Completed,
            'completed_at' => now(),
            'actual_cost' => round($spend, 2),
        ])->save();

        $reservation = $campaign->reservations()
            ->where('status', ReservationStatus::Held->value)
            ->latest('id')
            ->first();

        if ($reservation === null) {
            return;
        }

        $this->wallet->capture($reservation, round($spend, 2), 'Campaign spend for '.$campaign->name);

        $this->wallet->release(
            $reservation->refresh(),
            'Unused funds released for '.$campaign->name,
        );
    }
}
