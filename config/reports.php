<?php

/*
|--------------------------------------------------------------------------
| Analytics and reporting
|--------------------------------------------------------------------------
|
| Reports are read live from the delivery logs so a number is never stale, and
| the daily rollups written by `reports:aggregate` feed the trend charts. The
| export disk holds the CSV files a client downloads or a scheduled report
| attaches; the retention window is how long they are kept.
|
| `scheduled` is the recurring report the platform mails: a weekly or monthly
| window, the platform recipients who receive the executive summary, and a
| switch that keeps the schedule quiet until the deployment is ready for it.
|
*/

return [

    /*
    | The analytics overview reads this many days until the user picks a range.
    */
    'default_window_days' => (int) env('REPORTS_DEFAULT_WINDOW_DAYS', 30),

    'exports' => [
        'disk' => env('REPORTS_EXPORT_DISK', 'local'),
        'path' => env('REPORTS_EXPORT_PATH', 'reports'),

        /*
        | A guard against an accidental "export everything" run. The limit is
        | applied per report, not per client.
        */
        'max_rows' => (int) env('REPORTS_EXPORT_MAX_ROWS', 50000),

        /*
        | Minimum download period after legal approval. This value cannot
        | enable pruning or shorten the approved period.
        */
        'retention_days' => (int) env('REPORTS_EXPORT_RETENTION_DAYS', 30),
    ],

    /*
    | `reports:aggregate` rebuilds the last N days on every run, which repairs a
    | late receipt without a backfill script.
    */
    'aggregate' => [
        'window_days' => (int) env('REPORTS_AGGREGATE_WINDOW_DAYS', 7),
    ],

    /*
    | How far the usage ledger may drift from the campaign and wallet ledgers
    | before the reconciliation panel raises a variance.
    */
    'reconciliation' => [
        'tolerance' => (float) env('REPORTS_RECONCILIATION_TOLERANCE', 0.01),
    ],

    'scheduled' => [
        'enabled' => (bool) env('REPORTS_SCHEDULED_ENABLED', false),

        /*
        | `weekly` mails the previous calendar week, `monthly` the previous
        | calendar month.
        */
        'frequency' => env('REPORTS_SCHEDULED_FREQUENCY', 'weekly'),

        /*
        | The platform recipients of the executive summary. Each client's own
        | admins always receive that client's overview.
        |
        | @var list<string>
        */
        'recipients' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('REPORTS_SCHEDULED_RECIPIENTS', '')),
        ))),
    ],

];
