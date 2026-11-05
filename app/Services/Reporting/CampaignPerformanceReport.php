<?php

namespace App\Services\Reporting;

use App\Enums\CampaignContactStatus;
use App\Models\Campaign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * One row per campaign with the outcomes its audience produced.
 *
 * The delivery rate counts confirmed deliveries over everything that was
 * attempted, which is the same definition the per-channel reports use, so a
 * campaign reads the same on every screen.
 */
class CampaignPerformanceReport
{
    /**
     * @param  array{campaign?: int|string|null, channel?: string|null}  $filters
     * @return array{rows: list<array<string, mixed>>, totals: array<string, mixed>}
     */
    public function for(ReportScope $scope, ReportPeriod $period, array $filters = [], int $limit = 100): array
    {
        $channel = (string) ($filters['channel'] ?? '');
        $campaignId = (int) ($filters['campaign'] ?? 0);

        $query = $scope->campaigns()
            ->whereBetween('created_at', [$period->from, $period->to])
            ->when($channel !== '', fn (Builder $query): Builder => $query->where('channel', $channel))
            ->when($campaignId > 0, fn (Builder $query): Builder => $query->whereKey($campaignId));

        $totals = $this->totals($scope, clone $query);

        $rows = (clone $query)
            ->withCount($this->counts())
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Campaign $campaign): array => $this->row($campaign))
            ->values()
            ->all();

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * The status counts eager-loaded onto every campaign row.
     *
     * @return array<string, \Closure>
     */
    protected function counts(): array
    {
        return [
            'campaignContacts as delivered_count' => fn (Builder $query): Builder => $query
                ->where('status', CampaignContactStatus::Delivered->value),
            'campaignContacts as sent_count' => fn (Builder $query): Builder => $query
                ->where('status', CampaignContactStatus::Sent->value),
            'campaignContacts as failed_count' => fn (Builder $query): Builder => $query
                ->where('status', CampaignContactStatus::Failed->value),
            'campaignContacts as skipped_count' => fn (Builder $query): Builder => $query
                ->where('status', CampaignContactStatus::Skipped->value),
            'campaignContacts as pending_count' => fn (Builder $query): Builder => $query
                ->whereIn('status', [
                    CampaignContactStatus::Pending->value,
                    CampaignContactStatus::Queued->value,
                ]),
        ];
    }

    /**
     * @param  Builder<Campaign>  $query
     * @return array<string, mixed>
     */
    protected function totals(ReportScope $scope, Builder $query): array
    {
        $statusTotals = $scope->campaignContacts()
            ->whereIn('campaign_id', (clone $query)->select('id'))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $delivered = $this->count($statusTotals, CampaignContactStatus::Delivered)
            + $this->count($statusTotals, CampaignContactStatus::Sent);
        $failed = $this->count($statusTotals, CampaignContactStatus::Failed);
        $skipped = $this->count($statusTotals, CampaignContactStatus::Skipped);
        $attempted = $delivered + $failed + $skipped;

        return [
            'campaigns' => (clone $query)->count(),
            'recipients' => (int) $statusTotals->sum(),
            'delivered' => $delivered,
            'failed' => $failed,
            'skipped' => $skipped,
            'pending' => $this->count($statusTotals, CampaignContactStatus::Pending)
                + $this->count($statusTotals, CampaignContactStatus::Queued),
            'spend' => round((float) (clone $query)->sum('actual_cost'), 2),
            'delivery_rate' => $attempted > 0 ? round($delivered / $attempted * 100, 1) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(Campaign $campaign): array
    {
        $delivered = (int) $campaign->getAttribute('delivered_count') + (int) $campaign->getAttribute('sent_count');
        $failed = (int) $campaign->getAttribute('failed_count');
        $skipped = (int) $campaign->getAttribute('skipped_count');
        $attempted = $delivered + $failed + $skipped;

        return [
            'id' => $campaign->id,
            'reference' => $campaign->reference,
            'name' => $campaign->name,
            'channel' => $campaign->channel->value,
            'channel_label' => $campaign->channel->label(),
            'status' => $campaign->status->value,
            'status_label' => $campaign->status->label(),
            'recipients' => (int) $campaign->estimated_recipients,
            'delivered' => $delivered,
            'failed' => $failed,
            'skipped' => $skipped,
            'pending' => (int) $campaign->getAttribute('pending_count'),
            'spend' => (float) $campaign->actual_cost,
            'delivery_rate' => $attempted > 0 ? round($delivered / $attempted * 100, 1) : null,
            'created_at' => $campaign->created_at?->toIso8601String(),
            'launched_at' => $campaign->launched_at?->toIso8601String(),
            'completed_at' => $campaign->completed_at?->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<string, int|string>  $totals
     */
    protected function count(Collection $totals, CampaignContactStatus $status): int
    {
        return (int) $totals->get($status->value, 0);
    }
}
