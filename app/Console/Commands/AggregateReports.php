<?php

namespace App\Console\Commands;

use App\Services\Reporting\DailyAggregator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Rebuilds the daily delivery rollups the analytics screens trend.
 *
 * The command is safe to run hourly: it recomputes a whole day from the event
 * tables, so the current day is refreshed as recipients move and a rerun never
 * doubles a total.
 */
class AggregateReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:aggregate
        {--days= : How many days back to rebuild (defaults to the configured window)}
        {--date= : Rebuild a single day (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild the daily delivery rollups the reports trend from';

    /**
     * Execute the console command.
     */
    public function handle(DailyAggregator $aggregator): int
    {
        $date = $this->option('date');

        if (is_string($date) && $date !== '') {
            $rows = $aggregator->day(Carbon::parse($date)->startOfDay());

            $this->info("Rebuilt {$rows} rollup row(s) for {$date}.");

            return self::SUCCESS;
        }

        $days = max((int) ($this->option('days') ?: config('reports.aggregate.window_days', 7)), 1);
        $to = now()->startOfDay();
        $from = $to->copy()->subDays($days - 1);

        $rows = $aggregator->range($from, $to);

        $this->info("Rebuilt {$rows} rollup row(s) for {$from->toDateString()} to {$to->toDateString()}.");

        return self::SUCCESS;
    }
}
