<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Delivery queue topology
    |--------------------------------------------------------------------------
    |
    | Every broadcast is queued rather than sent inside the request that
    | launched it. Multi-channel campaigns are orchestrated on the `campaigns`
    | queue, while a single-channel campaign runs on its own channel queue so a
    | rate-limited SMS gateway, a slow voice PBX or a throttled mail relay can
    | never starve the other channels.
    |
    | The queue names here must match the supervisors in config/horizon.php and
    | the `redis` connection in config/queue.php.
    |
    */

    'campaigns' => env('QUEUE_CAMPAIGNS', 'campaigns'),

    /*
    |--------------------------------------------------------------------------
    | Per channel queues
    |--------------------------------------------------------------------------
    |
    | `cps` is the platform-wide ceiling for submissions per second; zero means
    | "as fast as the provider answers". It can be overridden at runtime with
    | the `rate_limits.<channel>_cps` platform setting or per client, which is
    | what a negotiated throughput agreement needs.
    |
    | `tries`, `backoff` and `timeout` are the worker policy used when a job
    | that submits on the channel is retried.
    |
    */

    'channels' => [
        'sms' => [
            'queue' => env('QUEUE_SMS', 'sms'),
            'cps' => (int) env('SMS_BROADCAST_GLOBAL_CPS', 0),
            'tries' => (int) env('QUEUE_SMS_TRIES', 3),
            'backoff' => [30, 120, 300],
            'timeout' => (int) env('QUEUE_SMS_TIMEOUT', 120),
        ],
        'voice' => [
            'queue' => env('QUEUE_VOICE', 'voice'),
            'cps' => (int) env('VOICE_BROADCAST_GLOBAL_CPS', 0),
            'tries' => (int) env('QUEUE_VOICE_TRIES', 3),
            'backoff' => [60, 180, 600],
            'timeout' => (int) env('QUEUE_VOICE_TIMEOUT', 300),
        ],
        'email' => [
            'queue' => env('QUEUE_EMAIL', 'email'),
            'cps' => (int) env('EMAIL_BROADCAST_GLOBAL_CPS', 0),
            'tries' => (int) env('QUEUE_EMAIL_TRIES', 3),
            'backoff' => [60, 300, 900],
            'timeout' => (int) env('QUEUE_EMAIL_TIMEOUT', 180),
        ],
        'whatsapp' => [
            'queue' => env('QUEUE_WHATSAPP', 'whatsapp'),
            'cps' => (int) env('WHATSAPP_BROADCAST_GLOBAL_CPS', 0),
            'tries' => (int) env('QUEUE_WHATSAPP_TRIES', 3),
            'backoff' => [30, 120, 300],
            'timeout' => (int) env('QUEUE_WHATSAPP_TIMEOUT', 120),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Campaign dispatch job
    |--------------------------------------------------------------------------
    |
    | The dispatcher itself is long running: one pass renders every message in
    | the audience and waits on the provider. It is therefore given a generous
    | timeout and an exponential backoff, and it is always retried on the queue
    | that owns the failure.
    |
    */

    'jobs' => [
        'campaign_dispatch' => [
            'tries' => (int) env('QUEUE_CAMPAIGN_TRIES', 3),
            'backoff' => [60, 300, 900],
            'timeout' => (int) env('QUEUE_CAMPAIGN_TIMEOUT', 900),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduler safety net
    |--------------------------------------------------------------------------
    |
    | A scheduled campaign is released as a delayed job at its start time. The
    | minute scheduler is the guarantee behind that job: if Redis was flushed,
    | a worker restarted mid-pass or a deployment dropped the delayed set, the
    | scheduler promotes the campaign and re-queues any campaign that has been
    | sitting on pending recipients for longer than `stalled_after_minutes`.
    |
    */

    'scheduler' => [
        'stalled_after_minutes' => (int) env('CAMPAIGN_STALLED_AFTER_MINUTES', 15),
        'sweep_limit' => (int) env('CAMPAIGN_SWEEP_LIMIT', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed job retention
    |--------------------------------------------------------------------------
    |
    | This minimum may extend the approved period. Failed-job pruning stays
    | disabled until a legal approval is recorded in Admin > Data retention.
    |
    */

    'failed' => [
        'prune_after_hours' => (int) env('QUEUE_FAILED_PRUNE_HOURS', 168),
    ],

];
