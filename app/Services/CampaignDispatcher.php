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
use App\Exceptions\InsufficientWalletBalance;
use App\Models\BillingReservation;
use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\Contact;
use App\Models\EmailAttachment;
use App\Services\Delivery\DeliveryPacer;
use App\Services\Email\OutboundAttachment;
use App\Services\Email\OutboundEmail;
use App\Services\Sms\OutboundSms;
use App\Services\Voice\VoiceCall as VoiceCallData;
use Illuminate\Support\Collection;

/**
 * Sends a campaign to its audience, bills the client for every unit it delivers
 * and closes the campaign out.
 */
class CampaignDispatcher
{
    public function __construct(
        protected SmsGateway $sms,
        protected VoiceGateway $voice,
        protected EmailGateway $email,
        protected CampaignCostEstimator $costs,
        protected SmsSegmenter $segmenter,
        protected MessagePersonalizer $personalizer,
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
        $campaign->loadMissing(['client', 'emailAttachments']);
        $disabledChannels = array_filter(
            $campaign->channel->deliveryChannels(),
            fn (Channel $channel): bool => ! $this->configuration->channelEnabled($channel->value, $campaign->client),
        );
        if ($disabledChannels !== []) {
            $this->failPending($campaign, 'Delivery was disabled by platform settings.');
            $this->finalize($campaign);

            return false;
        }

        $campaign->forceFill([
            'status' => CampaignStatus::Running,
            'launched_at' => $campaign->launched_at ?? now(),
        ])->save();

        $campaign->loadMissing(['senderId', 'callerId', 'client']);

        $blockers = $this->readiness->issues($campaign);

        if ($blockers !== []) {
            $this->failPending($campaign, 'Delivery blocked: '.$blockers[0]['label'].'.');
            $this->finalize($campaign);

            return false;
        }

        $reservation = $this->startBilling($campaign);

        if ($reservation === false) {
            $this->failPending($campaign, 'Delivery blocked: the wallet cannot cover this campaign.');
            $this->finalize($campaign);

            return false;
        }

        $smsPartCost = $this->costs->smsPartCost($campaign->client);
        $voiceCallCost = $this->costs->voiceCallCost($campaign->client);
        $emailRecipientCost = $this->costs->emailRecipientCost($campaign->client);

        $spend = $this->wallet->chargedFor($campaign);
        $broke = false;

        $campaign->campaignContacts()
            ->with('contact')
            ->whereIn('status', [CampaignContactStatus::Pending->value, CampaignContactStatus::Queued->value])
            ->chunkById(200, function (Collection $rows) use ($campaign, $reservation, $smsPartCost, $voiceCallCost, $emailRecipientCost, &$spend, &$broke): void {
                foreach ($rows as $row) {
                    if (! $this->dispatchTo($campaign, $reservation, $row, $smsPartCost, $voiceCallCost, $emailRecipientCost, $spend)) {
                        $broke = true;

                        return;
                    }
                }
            });

        if ($broke) {
            $this->failPending($campaign, 'Delivery stopped: the wallet cannot cover the rest of this campaign.');
            $this->finalize($campaign);

            return false;
        }

        if ($this->awaitingRetry($campaign)) {
            return true;
        }

        $this->finalize($campaign);

        return false;
    }

    /**
     * Open the hold the campaign is billed against before the first send.
     *
     * @return BillingReservation|false|null the hold, false when the wallet cannot cover it, or null when no rate applies
     */
    protected function startBilling(Campaign $campaign): BillingReservation|false|null
    {
        $remaining = round(max((float) $campaign->estimated_cost - $this->wallet->chargedFor($campaign), 0.0), WalletService::PRECISION);

        try {
            return $this->wallet->startBilling($campaign, $remaining);
        } catch (InsufficientWalletBalance) {
            return false;
        }
    }

    /**
     * Send the campaign to a single contact, bill every unit it consumed and
     * record the outcome.
     *
     * @return bool whether the campaign may carry on to the next recipient
     */
    protected function dispatchTo(
        Campaign $campaign,
        ?BillingReservation $reservation,
        CampaignContact $row,
        float $smsPartCost,
        float $voiceCallCost,
        float $emailCostPerRecipient,
        float &$spend,
    ): bool {
        $contact = $row->contact;

        if (! $contact instanceof Contact) {
            $row->forceFill(['status' => CampaignContactStatus::Skipped])->save();

            return true;
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

            return true;
        }

        $delivered = true;
        $failure = 'The gateway rejected the message.';
        $charged = 0.0;

        foreach ($campaign->channel->deliveryChannels() as $channel) {
            if (! ($channelDecisions[$channel->value]['eligible'] ?? false)) {
                continue;
            }

            $unitCost = match ($channel) {
                Channel::Sms => $this->smsCostFor($campaign, $contact, $smsPartCost),
                Channel::Voice => $voiceCallCost,
                Channel::Email => $emailCostPerRecipient,
                default => 0.0,
            };

            if (! $this->canAfford($reservation, $unitCost)) {
                $row->forceFill([
                    'status' => CampaignContactStatus::Failed,
                    'failed_at' => now(),
                    'error_message' => 'The wallet balance cannot cover this '.strtolower($channel->label()).'.',
                ])->save();

                return false;
            }

            $this->pacer->await($channel, $campaign->client);

            $delivered = match ($channel) {
                Channel::Sms => $this->sendSms($campaign, $row, $contact, $unitCost),
                Channel::Voice => $this->callVoice($campaign, $row, $contact, $voiceCallCost),
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

            if ($this->charge($campaign, $reservation, $channel, $unitCost, $row)) {
                $charged = round($charged + $unitCost, WalletService::PRECISION);
                $spend = round($spend + $unitCost, WalletService::PRECISION);
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
                'cost' => $charged,
            ])->save();

            return true;
        }

        $exhausted = $attempts >= max($campaign->max_retries, 1);

        $row->forceFill([
            'status' => $exhausted ? CampaignContactStatus::Failed : CampaignContactStatus::Queued,
            'attempts' => $attempts,
            'last_attempt_at' => $now,
            'failed_at' => $exhausted ? $now : null,
            'error_message' => $failure,
            'cost' => $charged,
        ])->save();

        return true;
    }

    /**
     * Whether the campaign can pay for the next billable unit.
     */
    protected function canAfford(?BillingReservation $reservation, float $unitCost): bool
    {
        if ($unitCost <= 0 || $reservation === null) {
            return true;
        }

        return $this->wallet->canAfford($reservation, $unitCost);
    }

    /**
     * Apply one billable unit to the wallet the campaign is running on.
     */
    protected function charge(Campaign $campaign, ?BillingReservation $reservation, Channel $channel, float $unitCost, CampaignContact $row): bool
    {
        if ($unitCost <= 0 || $reservation === null) {
            return false;
        }

        return $this->wallet->spend(
            $reservation,
            $unitCost,
            sprintf('%s to %s for %s', $channel->label(), $row->contact?->phone ?? $row->contact?->email ?? 'contact', $campaign->reference),
            ['channel' => $channel->value, 'campaign_contact_id' => $row->id],
        );
    }

    /**
     * The billable cost of the SMS this recipient will actually receive.
     *
     * The placeholder values change the length of the copy, so the segment count
     * the message is billed at is worked out from the rendered body rather than
     * from the template.
     */
    protected function smsCostFor(Campaign $campaign, Contact $contact, float $smsPartCost): float
    {
        $body = $this->personalizer->render((string) $campaign->sms_message, $contact);
        $parts = max($this->segmenter->segmentsFor($body), 1);

        return round($parts * $smsPartCost, WalletService::PRECISION);
    }

    /**
     * Log the message, then ask the provider to deliver it.
     *
     * The row is written before the submission so a receipt that arrives faster
     * than the provider response can still be matched to its message.
     */
    protected function sendSms(Campaign $campaign, CampaignContact $recipient, Contact $contact, float $cost): bool
    {
        $sender = $campaign->senderId;
        $from = trim((string) $sender?->name);
        $to = trim((string) $contact->phone);

        if ($to === '' || $from === '') {
            return false;
        }

        $body = $this->personalizer->render((string) $campaign->sms_message, $contact);

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
        $subject = trim($this->personalizer->render((string) $campaign->email_subject, $contact));
        $body = $this->personalizer->render((string) $campaign->email_body, $contact);

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
            attachments: $campaign->emailAttachments
                ->map(fn (EmailAttachment $attachment): OutboundAttachment => $attachment->toOutbound())
                ->all(),
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
     * Fail every recipient that has not been sent to yet.
     */
    protected function failPending(Campaign $campaign, string $message): void
    {
        $campaign->campaignContacts()
            ->whereIn('status', [CampaignContactStatus::Pending->value, CampaignContactStatus::Queued->value])
            ->update([
                'status' => CampaignContactStatus::Failed->value,
                'failed_at' => now(),
                'error_message' => $message,
            ]);
    }

    /**
     * Close the campaign, settle the ledger and give back the unused hold.
     */
    protected function finalize(Campaign $campaign): void
    {
        $charged = $this->wallet->chargedFor($campaign);

        $campaign->forceFill([
            'status' => CampaignStatus::Completed,
            'completed_at' => now(),
            'actual_cost' => $charged,
            'reserved_amount' => 0,
        ])->save();

        $campaign->reservations()
            ->where('status', ReservationStatus::Held->value)
            ->get()
            ->each(function (BillingReservation $reservation) use ($campaign): void {
                $this->wallet->release($reservation, 'Unused funds released for '.$campaign->name);
            });
    }
}
