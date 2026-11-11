<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Workflow automations
    |--------------------------------------------------------------------------
    |
    | A workflow is a trigger plus an ordered list of steps. Runs are worked
    | through by a queued job rather than inside the request that fired the
    | trigger, so a slow provider or a webhook step never delays a contact save.
    | `enabled` is the kill switch: with it off no run row is written.
    |
    */

    'enabled' => filter_var(env('AUTOMATIONS_ENABLED', true), FILTER_VALIDATE_BOOL),

    'queue' => env('AUTOMATIONS_QUEUE', 'default'),

    'job' => [
        'tries' => (int) env('AUTOMATIONS_TRIES', 3),
        'backoff' => [60, 300, 900],
        'timeout' => (int) env('AUTOMATIONS_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Step limits
    |--------------------------------------------------------------------------
    |
    | `max_steps_per_run` stops a workflow that loops back on itself from
    | starving a worker, and `max_wait_minutes` caps what a single wait step may
    | ask for so a workflow cannot park a contact for a year by accident.
    |
    */

    'max_steps_per_run' => (int) env('AUTOMATIONS_MAX_STEPS', 50),

    'max_wait_minutes' => (int) env('AUTOMATIONS_MAX_WAIT_MINUTES', 43200),

    'max_steps_per_workflow' => (int) env('AUTOMATIONS_MAX_WORKFLOW_STEPS', 15),

    /*
    |--------------------------------------------------------------------------
    | Sending defaults
    |--------------------------------------------------------------------------
    |
    | Automation messages are sent from the tenant's approved sender identity
    | when it has one, so a contact recognises the brand. The header values fall
    | back to the platform defaults when no approved identity exists.
    |
    */

    'sms' => [
        'from' => env('AUTOMATIONS_SMS_FROM', config('services.sms.sender_id', 'OmniCast')),
    ],

    'email' => [
        'from' => env('AUTOMATIONS_EMAIL_FROM', config('email.from.address')),
        'from_name' => env('AUTOMATIONS_EMAIL_FROM_NAME', config('email.from.name')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook step
    |--------------------------------------------------------------------------
    |
    | The webhook step posts `X-Omnicast-Automation: t=<unix>,v1=<hmac>` over the
    | raw body, signed with the endpoint secret the workflow stores, so a
    | receiver can verify the call exactly like a subscription delivery.
    |
    */

    'webhook' => [
        'secret_prefix' => env('AUTOMATIONS_WEBHOOK_SECRET_PREFIX', 'autsec_'),
        'signature_header' => 'X-Omnicast-Automation',
        'timeout' => (int) env('AUTOMATIONS_WEBHOOK_TIMEOUT', 10),
        'connect_timeout' => (int) env('AUTOMATIONS_WEBHOOK_CONNECT_TIMEOUT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Run retention
    |--------------------------------------------------------------------------
    |
    | This value may extend the approved period. Run pruning stays disabled
    | until a legal approval is recorded in Admin > Data retention.
    |
    */

    'retention_days' => (int) env('AUTOMATIONS_RETENTION_DAYS', 90),

];
