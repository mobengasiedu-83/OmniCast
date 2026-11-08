<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMPP server
    |--------------------------------------------------------------------------
    |
    | OmniCast listens as an SMSC for approved ESMEs: an integration binds with
    | the system ID and password issued on its SMPP account, submits `submit_sm`
    | and receives `deliver_sm` receipts. The listener is started with
    | `php artisan sms:smpp-server`; it is a daemon, so it belongs under a
    | supervisor rather than the web server.
    |
    | `enabled` is the kill switch: a switched-off server refuses every bind with
    | `ESME_RBINDFAIL` instead of silently accepting traffic it cannot deliver.
    |
    */

    'enabled' => filter_var(env('SMPP_SERVER_ENABLED', false), FILTER_VALIDATE_BOOL),

    'host' => env('SMPP_SERVER_HOST', '0.0.0.0'),

    'port' => (int) env('SMPP_SERVER_PORT', 2775),

    /*
    |--------------------------------------------------------------------------
    | Session policy
    |--------------------------------------------------------------------------
    |
    | `max_sessions` caps concurrent binds, `enquire_link_interval` is how often
    | the server probes a quiet session, and `idle_timeout` closes a session that
    | has not answered an `enquire_link`. A bind that is never used is dropped
    | after `session_ttl` so a leaked connection cannot hold a slot for ever.
    |
    */

    'limits' => [
        'max_sessions' => (int) env('SMPP_MAX_SESSIONS', 100),
        'enquire_link_interval' => (int) env('SMPP_ENQUIRE_LINK_INTERVAL', 30),
        'idle_timeout' => (int) env('SMPP_IDLE_TIMEOUT', 90),
        'session_ttl' => (int) env('SMPP_SESSION_TTL', 3600),
        'read_chunk_bytes' => (int) env('SMPP_READ_CHUNK_BYTES', 65536),
        'max_pdu_bytes' => (int) env('SMPP_MAX_PDU_BYTES', 65535),
        'accept_pause_seconds' => (int) env('SMPP_ACCEPT_PAUSE', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ingestion
    |--------------------------------------------------------------------------
    |
    | A `submit_sm` is stored as an `smpp_messages` row and handed to the `sms`
    | queue, which submits it through the configured SMS transport. The
    | delivery receipt is queued as a `deliver_sm` for the ESME that submitted
    | the message.
    |
    */

    'ingest' => [
        'queue' => env('SMPP_QUEUE', 'sms'),
        'tries' => (int) env('SMPP_TRIES', 3),
        'backoff' => [30, 120, 300],
        'timeout' => (int) env('SMPP_TIMEOUT', 120),
        'default_source' => env('SMPP_DEFAULT_SOURCE', 'OMNICAST'),
    ],

];
