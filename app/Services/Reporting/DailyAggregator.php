<?php

namespace App\Services\Reporting;

use App\Enums\CampaignContactStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the daily delivery rollups the dashboards trend.
 *
 * A day is recomputed in full and written in one transaction, so running the
 * command twice repairs the same day instead of doubling it, and a day whose
 * recipients were removed loses its row the next time it is rebuilt.
 */
class DailyAggregator
{
    /**
     * Rebuild one day and return the number of client/channel rows written.
     *
     * `now()` hands back an immutable instance, so the day is normalised to a
     * mutable `Carbon` before it is used.
     */
    public function day(CarbonInterface $date): int
    {
        $date = Carbon::instance($date);

        $rows = DB::table('campaign_contacts')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_contacts.campaign_id')
            ->whereNull('campaigns.deleted_at')
            ->whereDate('campaign_contacts.created_at', $date->toDateString())
            ->selectRaw(
                'campaigns.client_id as client_id, '
                .'date(campaign_contacts.created_at) as stat_date, '
                .'campaigns.channel as channel, '
                .'count(distinct campaigns.id) as campaigns, '
                .'count(*) as recipients, '
                .'sum(case when campaign_contacts.status = ? then 1 else 0 end) as delivered, '
                .'sum(case when campaign_contacts.status = ? then 1 else 0 end) as failed, '
                .'sum(case when campaign_contacts.status = ? then 1 else 0 end) as skipped, '
                .'coalesce(sum(campaign_contacts.cost), 0) as spend',
                [
                    CampaignContactStatus::Delivered->value,
                    CampaignContactStatus::Failed->value,
                    CampaignContactStatus::Skipped->value,
                ],
            )
            ->groupByRaw('campaigns.client_id, date(campaign_contacts.created_at), campaigns.channel')
            ->get();

        $payload = $rows
            ->map(fn ($row): array => [
                'client_id' => (int) $row->client_id,
                'date' => (string) $row->stat_date,
                'channel' => (string) $row->channel,
                'campaigns' => (int) $row->campaigns,
                'recipients' => (int) $row->recipients,
                'delivered' => (int) $row->delivered,
                'failed' => (int) $row->failed,
                'skipped' => (int) $row->skipped,
                'spend' => round((float) $row->spend, 4),
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        DB::transaction(function () use ($date, $payload): void {
            DB::table('report_daily_stats')
                ->whereDate('date', $date->toDateString())
                ->delete();

            if ($payload !== []) {
                DB::table('report_daily_stats')->insert($payload);
            }
        });

        return count($payload);
    }

    /**
     * Rebuild every day between two dates, oldest first.
     */
    public function range(CarbonInterface $from, CarbonInterface $to): int
    {
        $written = 0;
        $cursor = Carbon::instance($from)->startOfDay();
        $last = Carbon::instance($to)->startOfDay();

        while ($cursor->lessThanOrEqualTo($last)) {
            $written += $this->day($cursor->copy());
            $cursor->addDay();
        }

        return $written;
    }
}
