<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Enums\ClientService;
use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\ClientEntitlement;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class ClientQuotaService
{
    /**
     * Ensure recipient quota is available for each service used by a campaign.
     */
    public function assertAvailable(Campaign $campaign): void
    {
        $client = $campaign->client()->firstOrFail();
        $timezone = $client->timezone ?: 'UTC';
        $periodDate = ($campaign->scheduled_at ?? now())->copy()->setTimezone($timezone);

        foreach ($campaign->channel->deliveryChannels() as $channel) {
            $service = ClientService::fromChannel($channel);

            if ($service === null) {
                continue;
            }

            $entitlement = ClientEntitlement::query()
                ->where('client_id', $client->id)
                ->where('service', $service->value)
                ->lockForUpdate()
                ->first();

            if ($entitlement === null || ! $entitlement->is_enabled) {
                continue;
            }

            if ($entitlement->daily_quota !== null) {
                $start = $periodDate->copy()->startOfDay()->utc();
                $end = $periodDate->copy()->endOfDay()->utc();
                $used = $this->usage($campaign, $service, $start, $end);

                if ($used + $campaign->estimated_recipients > $entitlement->daily_quota) {
                    throw ValidationException::withMessages([
                        'channel' => "The {$service->label()} daily recipient limit has been reached.",
                    ]);
                }
            }

            if ($entitlement->monthly_quota !== null) {
                $start = $periodDate->copy()->startOfMonth()->startOfDay()->utc();
                $end = $periodDate->copy()->endOfMonth()->endOfDay()->utc();
                $used = $this->usage($campaign, $service, $start, $end);

                if ($used + $campaign->estimated_recipients > $entitlement->monthly_quota) {
                    throw ValidationException::withMessages([
                        'channel' => "The {$service->label()} monthly recipient limit has been reached.",
                    ]);
                }
            }
        }
    }

    private function usage(Campaign $campaign, ClientService $service, CarbonInterface $start, CarbonInterface $end): int
    {
        $channels = match ($service) {
            ClientService::Voice => [Channel::Voice->value, Channel::MultiChannel->value],
            ClientService::Sms => [Channel::Sms->value, Channel::MultiChannel->value],
            ClientService::Email => [Channel::Email->value],
            ClientService::WhatsApp => [],
        };

        if ($channels === []) {
            return 0;
        }

        return (int) CampaignContact::query()
            ->whereHas('campaign', function ($query) use ($campaign, $channels, $start, $end): void {
                $query->where('client_id', $campaign->client_id)
                    ->whereIn('channel', $channels)
                    ->whereNotNull('submitted_at')
                    ->whereNotIn('status', [
                        CampaignStatus::Draft->value,
                        CampaignStatus::Rejected->value,
                        CampaignStatus::Cancelled->value,
                    ])
                    ->whereRaw('COALESCE(scheduled_at, submitted_at) BETWEEN ? AND ?', [
                        $start->toDateTimeString(),
                        $end->toDateTimeString(),
                    ]);
            })
            ->where('campaign_id', '!=', $campaign->id)
            ->count();
    }
}
