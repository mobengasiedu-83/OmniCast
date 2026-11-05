<?php

namespace App\Services\Reporting;

use App\Enums\PaymentStatus;
use App\Models\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The cross-client performance table the platform team reconciles accounts on.
 *
 * Every client is listed, including one that has not broadcast yet, so a gap in
 * the numbers is visible instead of the row silently disappearing.
 */
class ClientPerformanceReport
{
    /**
     * @return array{rows: list<array<string, mixed>>, totals: array<string, mixed>}
     */
    public function for(ReportScope $scope, ReportPeriod $period): array
    {
        $campaigns = $this->campaignTotals($scope, $period);
        $outcomes = $this->outcomeTotals($scope, $period);
        $revenue = $this->revenueTotals($scope, $period);
        $wallets = $this->walletBalances($scope);

        $rows = $scope->clients()
            ->orderBy('name')
            ->get(['id', 'name', 'status'])
            ->map(fn (Client $client): array => $this->row($client, $campaigns, $outcomes, $revenue, $wallets))
            ->values()
            ->all();

        $totals = [
            'clients' => count($rows),
            'campaigns' => array_sum(array_column($rows, 'campaigns')),
            'recipients' => array_sum(array_column($rows, 'recipients')),
            'delivered' => array_sum(array_column($rows, 'delivered')),
            'failed' => array_sum(array_column($rows, 'failed')),
            'spend' => round(array_sum(array_column($rows, 'spend')), 2),
            'revenue' => round(array_sum(array_column($rows, 'revenue')), 2),
            'balance' => round(array_sum(array_column($rows, 'balance')), 2),
            'reserved' => round(array_sum(array_column($rows, 'reserved')), 2),
        ];

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * @return Collection<int|string, object>
     */
    protected function campaignTotals(ReportScope $scope, ReportPeriod $period): Collection
    {
        return DB::table('campaigns')
            ->whereNull('campaigns.deleted_at')
            ->whereBetween('campaigns.created_at', [$period->from, $period->to])
            ->when(
                $scope->clientId !== null,
                fn ($query) => $query->where('campaigns.client_id', $scope->clientId),
            )
            ->selectRaw('campaigns.client_id as client_id, count(*) as campaigns, coalesce(sum(campaigns.estimated_recipients), 0) as recipients, coalesce(sum(campaigns.actual_cost), 0) as spend')
            ->groupBy('campaigns.client_id')
            ->get()
            ->keyBy('client_id');
    }

    /**
     * @return Collection<int|string, object>
     */
    protected function outcomeTotals(ReportScope $scope, ReportPeriod $period): Collection
    {
        return DB::table('campaign_contacts')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_contacts.campaign_id')
            ->whereNull('campaigns.deleted_at')
            ->whereBetween('campaign_contacts.created_at', [$period->from, $period->to])
            ->when(
                $scope->clientId !== null,
                fn ($query) => $query->where('campaigns.client_id', $scope->clientId),
            )
            ->selectRaw(
                'campaigns.client_id as client_id, count(*) as recipients, '
                ."sum(case when campaign_contacts.status in ('delivered', 'sent') then 1 else 0 end) as delivered, "
                ."sum(case when campaign_contacts.status = 'failed' then 1 else 0 end) as failed, "
                ."sum(case when campaign_contacts.status = 'skipped' then 1 else 0 end) as skipped",
            )
            ->groupBy('campaigns.client_id')
            ->get()
            ->keyBy('client_id');
    }

    /**
     * @return Collection<int|string, object>
     */
    protected function revenueTotals(ReportScope $scope, ReportPeriod $period): Collection
    {
        return DB::table('payments')
            ->where('status', PaymentStatus::Paid->value)
            ->whereBetween('paid_at', [$period->from, $period->to])
            ->when(
                $scope->clientId !== null,
                fn ($query) => $query->where('client_id', $scope->clientId),
            )
            ->selectRaw('client_id, coalesce(sum(amount_paid), 0) as revenue')
            ->groupBy('client_id')
            ->get()
            ->keyBy('client_id');
    }

    /**
     * @return Collection<int|string, object>
     */
    protected function walletBalances(ReportScope $scope): Collection
    {
        return DB::table('wallets')
            ->when(
                $scope->clientId !== null,
                fn ($query) => $query->where('client_id', $scope->clientId),
            )
            ->selectRaw('client_id, balance, reserved_balance')
            ->get()
            ->keyBy('client_id');
    }

    /**
     * @param  Collection<int|string, object>  $campaigns
     * @param  Collection<int|string, object>  $outcomes
     * @param  Collection<int|string, object>  $revenue
     * @param  Collection<int|string, object>  $wallets
     * @return array<string, mixed>
     */
    protected function row(
        Client $client,
        Collection $campaigns,
        Collection $outcomes,
        Collection $revenue,
        Collection $wallets,
    ): array {
        $campaign = $campaigns->get($client->id);
        $outcome = $outcomes->get($client->id);
        $wallet = $wallets->get($client->id);
        $paid = $revenue->get($client->id);

        $delivered = (int) ($outcome->delivered ?? 0);
        $failed = (int) ($outcome->failed ?? 0);
        $skipped = (int) ($outcome->skipped ?? 0);
        $attempted = $delivered + $failed + $skipped;

        return [
            'id' => $client->id,
            'name' => $client->name,
            'status' => $client->status->value,
            'status_label' => $client->status->label(),
            'campaigns' => (int) ($campaign->campaigns ?? 0),
            'recipients' => (int) ($outcome->recipients ?? 0),
            'delivered' => $delivered,
            'failed' => $failed,
            'skipped' => $skipped,
            'delivery_rate' => $attempted > 0 ? round($delivered / $attempted * 100, 1) : null,
            'spend' => round((float) ($campaign->spend ?? 0), 2),
            'revenue' => round((float) ($paid->revenue ?? 0), 2),
            'balance' => round((float) ($wallet->balance ?? 0), 2),
            'reserved' => round((float) ($wallet->reserved_balance ?? 0), 2),
        ];
    }
}
