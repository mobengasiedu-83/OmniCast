<?php

namespace App\Services\Reporting;

use App\Enums\CampaignContactStatus;
use App\Enums\ClientStatus;
use App\Enums\PaymentStatus;
use App\Models\Client;
use App\Models\ReportDailyStat;

/**
 * The platform team's cross-client view of the business.
 *
 * Delivery headlines come from the same overview a tenant sees, so the platform
 * total is the sum of what each client is shown rather than a second opinion.
 * The daily trend reads the rollups when `reports:aggregate` has run and falls
 * back to the event tables when it has not.
 */
class ExecutiveReport
{
    public function __construct(protected OverviewReport $overview) {}

    /**
     * @return array<string, mixed>
     */
    public function for(ReportScope $scope, ReportPeriod $period): array
    {
        $overview = $this->overview->for($scope, $period);

        $clients = $scope->clients();
        $balances = $scope->wallets()
            ->selectRaw('coalesce(sum(balance), 0) as balance, coalesce(sum(reserved_balance), 0) as reserved')
            ->first();

        $revenue = $scope->payments()
            ->where('status', PaymentStatus::Paid->value)
            ->whereBetween('paid_at', [$period->from, $period->to]);

        return [
            'summary' => [
                ...$overview['summary'],
                'clients' => (clone $clients)->count(),
                'active_clients' => (clone $clients)->where('status', ClientStatus::Active->value)->count(),
                'new_clients' => (clone $clients)->whereBetween('created_at', [$period->from, $period->to])->count(),
                'revenue' => round((float) (clone $revenue)->sum('amount_paid'), 2),
                'payments' => (clone $revenue)->count(),
                'wallet_balance' => round((float) ($balances->balance ?? 0), 2),
                'wallet_reserved' => round((float) ($balances->reserved ?? 0), 2),
            ],
            'byChannel' => $overview['byChannel'],
            'monthly' => $overview['monthly'],
            'topClients' => $this->topClients($scope, $period),
            'trend' => $this->trend($scope, $period),
        ];
    }

    /**
     * The five clients that spent the most inside the window.
     *
     * @return list<array<string, mixed>>
     */
    protected function topClients(ReportScope $scope, ReportPeriod $period): array
    {
        $rows = $scope->campaigns()
            ->whereBetween('created_at', [$period->from, $period->to])
            ->selectRaw('client_id, count(*) as campaigns, coalesce(sum(estimated_recipients), 0) as recipients, coalesce(sum(actual_cost), 0) as spend')
            ->groupBy('client_id')
            ->orderByDesc('spend')
            ->limit(5)
            ->get();

        $names = Client::query()
            ->whereIn('id', $rows->pluck('client_id')->all())
            ->pluck('name', 'id');

        return $rows
            ->map(fn ($row): array => [
                'client_id' => (int) $row->client_id,
                'name' => (string) $names->get($row->client_id, 'Unknown client'),
                'campaigns' => (int) $row->campaigns,
                'recipients' => (int) $row->recipients,
                'spend' => round((float) $row->spend, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * A day-by-day trend, read from the rollups when they exist.
     *
     * @return list<array<string, mixed>>
     */
    protected function trend(ReportScope $scope, ReportPeriod $period): array
    {
        $rollups = ReportDailyStat::query()
            ->when(
                $scope->clientId !== null,
                fn ($query) => $query->where('client_id', $scope->clientId),
            )
            ->whereBetween('date', $period->dateRange())
            ->selectRaw('date, sum(recipients) as recipients, sum(delivered) as delivered, sum(failed) as failed, coalesce(sum(spend), 0) as spend')
            ->groupBy('date')
            ->orderBy('date')
            ->limit(120)
            ->get();

        if ($rollups->isNotEmpty()) {
            return $rollups
                ->map(fn ($row): array => [
                    'date' => (string) $row->date,
                    'recipients' => (int) $row->recipients,
                    'delivered' => (int) $row->delivered,
                    'failed' => (int) $row->failed,
                    'spend' => round((float) $row->spend, 2),
                ])
                ->values()
                ->all();
        }

        $delivered = [
            CampaignContactStatus::Delivered->value,
            CampaignContactStatus::Sent->value,
        ];

        $deliveredPlaceholders = implode(', ', array_fill(0, count($delivered), '?'));

        return $scope->campaignContacts()
            ->whereBetween('campaign_contacts.created_at', [$period->from, $period->to])
            ->selectRaw(
                'date(campaign_contacts.created_at) as date, count(*) as recipients, '
                ."sum(case when status in ({$deliveredPlaceholders}) then 1 else 0 end) as delivered, "
                .'sum(case when status = ? then 1 else 0 end) as failed, '
                .'coalesce(sum(campaign_contacts.cost), 0) as spend',
                [...$delivered, CampaignContactStatus::Failed->value],
            )
            ->groupByRaw('date(campaign_contacts.created_at)')
            ->orderBy('date')
            ->limit(120)
            ->get()
            ->map(fn ($row): array => [
                'date' => (string) $row->date,
                'recipients' => (int) $row->recipients,
                'delivered' => (int) $row->delivered,
                'failed' => (int) $row->failed,
                'spend' => round((float) $row->spend, 2),
            ])
            ->values()
            ->all();
    }
}
