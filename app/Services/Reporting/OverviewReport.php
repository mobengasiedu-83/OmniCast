<?php

namespace App\Services\Reporting;

use App\Enums\CampaignContactStatus;
use App\Enums\CampaignStatus;
use App\Enums\Channel;
use App\Models\Campaign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The delivery and spend overview a tenant lands on.
 *
 * Volume is counted from the recipients that moved inside the window, while
 * spend is the captured cost on campaigns created inside it, so the headline
 * numbers answer "what happened" and "what did it cost" separately.
 */
class OverviewReport
{
    /**
     * The summary, channel breakdown, six-month trend and top campaigns.
     *
     * @return array<string, mixed>
     */
    public function for(ReportScope $scope, ReportPeriod $period, string $channel = ''): array
    {
        $campaigns = $scope->campaigns()
            ->whereBetween('created_at', [$period->from, $period->to])
            ->when($channel !== '', fn (Builder $query): Builder => $query->where('channel', $channel));

        $recipients = $scope->campaignContacts()
            ->whereBetween('campaign_contacts.created_at', [$period->from, $period->to])
            ->whereHas('campaign', fn (Builder $query): Builder => $query
                ->when($channel !== '', fn (Builder $query): Builder => $query->where('channel', $channel)));

        $statusTotals = (clone $recipients)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $delivered = $this->count($statusTotals, CampaignContactStatus::Delivered)
            + $this->count($statusTotals, CampaignContactStatus::Sent);
        $failed = $this->count($statusTotals, CampaignContactStatus::Failed);
        $skipped = $this->count($statusTotals, CampaignContactStatus::Skipped);
        $pending = $this->count($statusTotals, CampaignContactStatus::Pending)
            + $this->count($statusTotals, CampaignContactStatus::Queued);
        $attempted = $delivered + $failed + $skipped;

        return [
            'summary' => [
                'campaigns' => (clone $campaigns)->count(),
                'completed_campaigns' => (clone $campaigns)->where('status', CampaignStatus::Completed->value)->count(),
                'recipients' => (int) $statusTotals->sum(),
                'delivered' => $delivered,
                'pending' => $pending,
                'failed' => $failed,
                'skipped' => $skipped,
                'spend' => round((float) (clone $campaigns)->sum('actual_cost'), 2),
                'reserved' => round((float) (clone $campaigns)->sum('reserved_amount'), 2),
                'delivery_rate' => $attempted > 0 ? round($delivered / $attempted * 100, 1) : null,
            ],
            'byChannel' => $this->byChannel($scope, $period),
            'monthly' => $this->monthly($scope),
            'topCampaigns' => $this->topCampaigns($campaigns),
        ];
    }

    /**
     * One row per campaign channel, so a tenant sees where its volume sits.
     *
     * @return list<array<string, mixed>>
     */
    protected function byChannel(ReportScope $scope, ReportPeriod $period): array
    {
        return collect(Channel::cases())
            ->map(function (Channel $channel) use ($scope, $period): array {
                $query = $scope->campaigns()
                    ->where('channel', $channel->value)
                    ->whereBetween('created_at', [$period->from, $period->to]);

                return [
                    'channel' => $channel->value,
                    'label' => $channel->label(),
                    'campaigns' => (clone $query)->count(),
                    'recipients' => (int) (clone $query)->sum('estimated_recipients'),
                    'spend' => round((float) (clone $query)->sum('actual_cost'), 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The last six calendar months of volume and spend.
     *
     * @return list<array<string, mixed>>
     */
    protected function monthly(ReportScope $scope): array
    {
        return collect(range(5, 0))
            ->map(function (int $monthsAgo) use ($scope): array {
                $month = now()->subMonthsNoOverflow($monthsAgo);
                $query = $scope->campaigns()
                    ->whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month);

                return [
                    'month' => $month->format('Y-m'),
                    'label' => $month->format('M Y'),
                    'campaigns' => (clone $query)->count(),
                    'spend' => round((float) (clone $query)->sum('actual_cost'), 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Builder<Campaign>  $campaigns
     * @return list<array<string, mixed>>
     */
    protected function topCampaigns(Builder $campaigns): array
    {
        return (clone $campaigns)
            ->orderByDesc('actual_cost')
            ->limit(5)
            ->get(['id', 'reference', 'name', 'channel', 'actual_cost', 'estimated_recipients'])
            ->map(fn (Campaign $campaign): array => [
                'id' => $campaign->id,
                'reference' => $campaign->reference,
                'name' => $campaign->name,
                'channel_label' => $campaign->channel->label(),
                'recipients' => $campaign->estimated_recipients,
                'spend' => (float) $campaign->actual_cost,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<string, int|string>  $totals
     */
    protected function count(Collection $totals, CampaignContactStatus $status): int
    {
        return (int) $totals->get($status->value, 0);
    }
}
