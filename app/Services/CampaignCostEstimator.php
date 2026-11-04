<?php

namespace App\Services;

use App\Enums\Channel;
use App\Enums\SmsEncoding;
use App\Models\Client;

/**
 * Estimates the recipient count, SMS parts and billable cost of a campaign.
 */
class CampaignCostEstimator
{
    public function __construct(
        protected RateResolver $rates,
        protected SmsSegmenter $segmenter,
    ) {}

    /**
     * Estimate the cost of broadcasting to the given number of recipients.
     *
     * Multi-channel campaigns are billed for both the SMS and the voice leg. Voice is
     * estimated as a single billed call per recipient, matching our per-call rates,
     * and an email broadcast is billed once per recipient.
     *
     * @return array{
     *     recipients: int,
     *     sms_parts: int,
     *     encoding: SmsEncoding|null,
     *     unit_price: float|null,
     *     voice_unit_price: float|null,
     *     email_unit_price: float|null,
     *     estimated_cost: float
     * }
     */
    public function estimate(
        Client $client,
        Channel $channel,
        ?string $smsMessage,
        int $recipients,
        string $destination = 'GH',
    ): array {
        $recipients = max($recipients, 0);

        $encoding = null;
        $parts = 1;

        if ($channel->requiresSmsContent()) {
            $encoding = $smsMessage === null || $smsMessage === ''
                ? SmsEncoding::Gsm7
                : $this->segmenter->encodingFor($smsMessage);

            $parts = $smsMessage === null || $smsMessage === ''
                ? 1
                : $this->segmenter->segmentsFor($smsMessage);
        }

        $smsUnitPrice = $channel->requiresSmsContent()
            ? $this->rates->unitPrice($client, Channel::Sms, $destination)
            : null;

        $voiceUnitPrice = $channel->requiresVoiceContent()
            ? $this->rates->unitPrice($client, Channel::Voice, $destination)
            : null;

        $emailUnitPrice = $channel->requiresEmailContent()
            ? $this->rates->unitPrice($client, Channel::Email, $destination)
            : null;

        $smsCost = $smsUnitPrice === null ? 0.0 : $smsUnitPrice * $parts * $recipients;
        $voiceCost = $voiceUnitPrice === null ? 0.0 : $voiceUnitPrice * $recipients;
        $emailCost = $emailUnitPrice === null ? 0.0 : $emailUnitPrice * $recipients;

        return [
            'recipients' => $recipients,
            'sms_parts' => $parts,
            'encoding' => $encoding,
            'unit_price' => $smsUnitPrice,
            'voice_unit_price' => $voiceUnitPrice,
            'email_unit_price' => $emailUnitPrice,
            'estimated_cost' => round($smsCost + $voiceCost + $emailCost, 2),
        ];
    }

    /**
     * The cost of a single SMS part, used for per-recipient billing during dispatch.
     */
    public function smsPartCost(Client $client, string $destination = 'GH'): float
    {
        return $this->rates->unitPrice($client, Channel::Sms, $destination) ?? 0.0;
    }

    /**
     * The cost of a single voice call, used for per-recipient billing during dispatch.
     */
    public function voiceCallCost(Client $client, string $destination = 'GH'): float
    {
        return $this->rates->unitPrice($client, Channel::Voice, $destination) ?? 0.0;
    }

    /**
     * The cost of a single email, used for per-recipient billing during dispatch.
     */
    public function emailRecipientCost(Client $client, string $destination = 'GH'): float
    {
        return $this->rates->unitPrice($client, Channel::Email, $destination) ?? 0.0;
    }
}
