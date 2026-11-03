<?php

namespace App\Services;

use App\Contracts\SmsGateway;
use App\Contracts\VoiceGateway;
use App\Enums\CampaignContactStatus;
use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Enums\ReservationStatus;
use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\Contact;
use Illuminate\Support\Collection;

/**
 * Sends a campaign to its audience, bills the client and closes the campaign out.
 */
class CampaignDispatcher
{
    public function __construct(
        protected SmsGateway $sms,
        protected VoiceGateway $voice,
        protected CampaignCostEstimator $costs,
        protected SmsSegmenter $segmenter,
        protected WalletService $wallet,
    ) {}

    /**
     * Send one pass over the campaign audience.
     *
     * @return bool whether recipients are still waiting for a retry
     */
    public function run(Campaign $campaign): bool
    {
        $campaign->forceFill([
            'status' => CampaignStatus::Running,
            'launched_at' => $campaign->launched_at ?? now(),
        ])->save();

        $campaign->loadMissing(['senderId', 'callerId', 'client']);

        $smsPartCost = $this->costs->smsPartCost($campaign->client);
        $voiceCallCost = $this->costs->voiceCallCost($campaign->client);
        $smsParts = $campaign->channel->requiresSmsContent()
            ? max($this->segmenter->segmentsFor((string) $campaign->sms_message), 1)
            : 0;

        $costPerRecipient = round(($smsParts * $smsPartCost) + ($campaign->channel->requiresVoiceContent() ? $voiceCallCost : 0), 4);

        $spend = (float) $campaign->campaignContacts()->sum('cost');

        $campaign->campaignContacts()
            ->with('contact')
            ->whereIn('status', [CampaignContactStatus::Pending->value, CampaignContactStatus::Queued->value])
            ->chunkById(200, function (Collection $rows) use ($campaign, $costPerRecipient, &$spend): void {
                foreach ($rows as $row) {
                    $this->dispatchTo($campaign, $row, $costPerRecipient, $spend);
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
    protected function dispatchTo(Campaign $campaign, CampaignContact $row, float $costPerRecipient, float &$spend): void
    {
        $contact = $row->contact;

        if (! $contact instanceof Contact || ! $this->isReachable($contact)) {
            $row->forceFill(['status' => CampaignContactStatus::Skipped])->save();

            return;
        }

        $delivered = true;

        foreach ($campaign->channel->deliveryChannels() as $channel) {
            $delivered = match ($channel) {
                Channel::Sms => $this->sms->send(
                    $contact->phone,
                    $campaign->senderId->name ?? 'OMNICAST',
                    (string) $campaign->sms_message,
                ),
                Channel::Voice => $this->voice->call(
                    $contact->phone,
                    $campaign->callerId->number ?? 'OMNICAST',
                    (string) $campaign->voice_script,
                    $campaign->voice_recording_path,
                ),
                default => false,
            };

            if (! $delivered) {
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
            'error_message' => 'The gateway rejected the message.',
        ])->save();
    }

    /**
     * Determine whether the contact may still be contacted.
     */
    protected function isReachable(Contact $contact): bool
    {
        return ! $contact->is_do_not_contact && $contact->consent_status->allowsBroadcast();
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
