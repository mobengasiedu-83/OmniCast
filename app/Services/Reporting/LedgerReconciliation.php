<?php

namespace App\Services\Reporting;

use App\Enums\WalletTransactionType;
use App\Models\Campaign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Checks the three ledgers a spend figure is claimed from.
 *
 * The event ledger is what the channels recorded (a cost per message and call),
 * the campaign ledger is what the dispatcher closed the campaign with, and the
 * billing ledger is what the wallet was actually debited. A report is only
 * trustworthy when all three agree, so the reports show the variance instead of
 * hiding it.
 */
class LedgerReconciliation
{
    /**
     * @return array<string, mixed>
     */
    public function for(ReportScope $scope, ReportPeriod $period): array
    {
        $tolerance = (float) config('reports.reconciliation.tolerance', 0.01);

        $campaigns = $scope->campaigns()->whereBetween('completed_at', [$period->from, $period->to]);
        $campaignIds = (clone $campaigns)->select('id');

        $usage = [
            'sms' => round((float) $scope->sms()->whereIn('campaign_id', $campaignIds)->sum('cost'), 4),
            'voice' => round((float) $scope->voice()->whereIn('campaign_id', $campaignIds)->sum('cost'), 4),
            'email' => round((float) $scope->email()->whereIn('campaign_id', $campaignIds)->sum('cost'), 4),
        ];
        $usageTotal = round(array_sum($usage), 4);

        $campaignCost = round((float) (clone $campaigns)->sum('actual_cost'), 2);
        $charged = $this->charges($scope, $campaignIds);
        $ledger = round((float) $charged->sum(), 2);

        $rows = (clone $campaigns)
            ->get(['id', 'reference', 'name', 'actual_cost'])
            ->map(function (Campaign $campaign) use ($charged): array {
                $claimed = round((float) $campaign->actual_cost, 2);
                $debited = round((float) $charged->get($campaign->id, 0), 2);

                return [
                    'id' => $campaign->id,
                    'reference' => $campaign->reference,
                    'name' => $campaign->name,
                    'claimed' => $claimed,
                    'debited' => $debited,
                    'variance' => round($claimed - $debited, 2),
                ];
            })
            ->values();

        $mismatches = $rows
            ->filter(fn (array $row): bool => abs($row['variance']) > $tolerance)
            ->take(10)
            ->values()
            ->all();

        return [
            'period' => $period->label(),
            'usage' => [...$usage, 'total' => $usageTotal],
            'campaign_cost' => $campaignCost,
            'ledger' => $ledger,
            'variance' => round($campaignCost - $ledger, 2),
            'usage_variance' => round($usageTotal - $campaignCost, 2),
            'tolerance' => $tolerance,
            'balanced' => abs($campaignCost - $ledger) <= $tolerance
                && abs($usageTotal - $campaignCost) <= $tolerance,
            'campaigns' => $mismatches,
        ];
    }

    /**
     * What the wallet was debited for each campaign in the window.
     *
     * @param  Builder<Campaign>  $campaignIds
     * @return Collection<int|string, float>
     */
    protected function charges(ReportScope $scope, $campaignIds): Collection
    {
        return $scope->walletTransactions()
            ->where('type', WalletTransactionType::Debit->value)
            ->whereNotNull('campaign_id')
            ->whereIn('campaign_id', $campaignIds)
            ->selectRaw('campaign_id, sum(amount) as total')
            ->groupBy('campaign_id')
            ->pluck('total', 'campaign_id')
            ->map(fn ($total): float => round((float) $total, 2));
    }
}
