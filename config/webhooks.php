<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Webhook subscriptions
    |--------------------------------------------------------------------------
    |
    | Outbound callbacks are queued rather than sent inside the request that
    | changed the campaign, so a slow subscriber can never slow a launch down.
    | `enabled` is the kill switch: with it off no delivery row is written.
    |
    */

    'enabled' => filter_var(env('WEBHOOKS_ENABLED', true), FILTER_VALIDATE_BOOL),

    'queue' => env('WEBHOOKS_QUEUE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Delivery policy
    |--------------------------------------------------------------------------
    |
    | A delivery is retried `tries` times with the given backoff. An endpoint
    | that fails `disable_after_failures` times in a row is switched off, so a
    | dead URL stops consuming workers until an operator looks at it.
    |
    */

    'delivery' => [
        'tries' => (int) env('WEBHOOKS_TRIES', 5),
        'backoff' => [30, 120, 600, 1800],
        'timeout' => (int) env('WEBHOOKS_TIMEOUT', 15),
        'connect_timeout' => (int) env('WEBHOOKS_CONNECT_TIMEOUT', 5),
        'max_response_bytes' => (int) env('WEBHOOKS_MAX_RESPONSE_BYTES', 4096),
        'disable_after_failures' => (int) env('WEBHOOKS_DISABLE_AFTER_FAILURES', 10),
        'user_agent' => env('WEBHOOKS_USER_AGENT', 'OmniCast-Webhooks/1.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Signing
    |--------------------------------------------------------------------------
    |
    | Every request carries `X-Omnicast-Signature: t=<unix>,v1=<hmac>` where the
    | HMAC is SHA-256 over `<unix>.<raw body>` with the endpoint secret. A
    | subscriber should reject a timestamp older than `tolerance` seconds.
    |
    */

    'signature' => [
        'header' => 'X-Omnicast-Signature',
        'secret_prefix' => env('WEBHOOKS_SECRET_PREFIX', 'whsec_'),
        'tolerance' => (int) env('WEBHOOKS_SIGNATURE_TOLERANCE', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery log retention
    |--------------------------------------------------------------------------
    |
    | `webhooks:prune-deliveries` deletes terminal deliveries older than this so
    | the audit table does not grow without bound.
    |
    */

    'retention_days' => (int) env('WEBHOOKS_RETENTION_DAYS', 30),

];
