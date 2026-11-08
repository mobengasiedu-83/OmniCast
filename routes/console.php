<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Delivery schedule
|--------------------------------------------------------------------------
|
| `schedule:run` is the heartbeat of the delivery engine: it releases campaigns
| whose start time has arrived, re-queues campaigns that lost their job and
| records the Horizon metrics the queue dashboard is charted from. Run it from
| cron (or `php artisan schedule:work` while developing) next to `horizon`.
|
*/

Schedule::command('campaigns:dispatch-scheduled')
    ->description('Queue campaigns whose scheduled start time has arrived')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('campaigns:sweep')
    ->description('Re-queue campaigns that stopped making progress')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('horizon:snapshot')
    ->description('Record the queue metrics Horizon charts')
    ->everyFiveMinutes()
    ->onOneServer();

Schedule::command('queue:prune-failed --hours='.config('queues.failed.prune_after_hours', 168))
    ->description('Prune failed jobs that are past their retention window')
    ->daily();

Schedule::command('queue:prune-batches --hours=48')
    ->description('Prune completed job batches')
    ->daily();

Schedule::command('webhooks:prune-deliveries')
    ->description('Delete finished webhook deliveries past their retention window')
    ->daily()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Reporting schedule
|--------------------------------------------------------------------------
|
| The rollups are rebuilt hourly so the current day is fresh; the scheduled
| report mails the period that just closed every morning and decides for itself
| whether the window is due. Files are pruned daily.
|
*/

Schedule::command('reports:aggregate')
    ->description('Rebuild the daily delivery rollups the reports trend from')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('reports:send-scheduled')
    ->description('Email the scheduled weekly or monthly report')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('reports:prune-exports')
    ->description('Delete report files past their retention window')
    ->daily();

/*
|--------------------------------------------------------------------------
| Automation schedule
|--------------------------------------------------------------------------
|
| A scheduled workflow stores the hour it wants, so the trigger runs every hour
| and starts only the workflows that are due. Finished runs are pruned daily so
| the history screen stays quick.
|
*/

Schedule::command('automations:run-scheduled')
    ->description('Start the automation workflows whose scheduled hour has arrived')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('automations:prune-runs')
    ->description('Delete finished automation runs past their retention window')
    ->daily()
    ->withoutOverlapping();
