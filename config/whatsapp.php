<?php

return [
    'sending_enabled' => (bool) env('WHATSAPP_ENABLED', false),
    'provider' => [
        'base_url' => env('WHATSAPP_API_BASE_URL'),
        'client_id' => env('WHATSAPP_API_CLIENT_ID'),
        'api_key' => env('WHATSAPP_API_KEY'),
        'endpoints' => [
            'connectors' => env('WHATSAPP_CONNECTORS_ENDPOINT', '/api/whatsapp/v1/Connector/GetAll'),
            'template' => env('WHATSAPP_TEMPLATE_ENDPOINT', '/api/whatsapp/v1/Template/Get'),
            'templates' => env('WHATSAPP_TEMPLATES_ENDPOINT', '/api/whatsapp/v1/Template/GetAll'),
            'send' => env('WHATSAPP_SEND_ENDPOINT', '/api/whatsapp/v1/Send'),
        ],
    ],
    'meta' => [
        'app_id' => env('META_WHATSAPP_APP_ID'),
        'app_secret' => env('META_WHATSAPP_APP_SECRET'),
        'embedded_signup_config_id' => env('META_WHATSAPP_EMBEDDED_SIGNUP_CONFIG_ID'),
        'solution_id' => env('META_WHATSAPP_SOLUTION_ID'),
        'graph_version' => env('META_GRAPH_API_VERSION', 'v26.0'),
        'verify_token' => env('META_WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        'onboarding_enabled' => (bool) env('META_WHATSAPP_ONBOARDING_ENABLED', false),
    ],
    'media' => [
        'disk' => env('WHATSAPP_MEDIA_DISK', 'local'),
        'max_kilobytes' => (int) env('WHATSAPP_MEDIA_MAX_KILOBYTES', 16384),
    ],
    'billing' => [
        'unknown_hold_days' => (int) env('WHATSAPP_UNKNOWN_HOLD_DAYS', 30),
    ],
    'events' => [
        'contract_enabled' => (bool) env('WHATSAPP_EVENTS_CONTRACT_ENABLED', false),
        'provider_id' => env('WHATSAPP_EVENTS_PROVIDER_ID'),
        'signing_secret' => env('WHATSAPP_EVENTS_SIGNING_SECRET'),
    ],
    'dispatch' => [
        'batch_size' => (int) env('WHATSAPP_DISPATCH_BATCH_SIZE', 25),
        'max_attempts' => (int) env('WHATSAPP_DISPATCH_MAX_ATTEMPTS', 5),
        'base_backoff_seconds' => (int) env('WHATSAPP_DISPATCH_BASE_BACKOFF_SECONDS', 30),
        'max_backoff_seconds' => (int) env('WHATSAPP_DISPATCH_MAX_BACKOFF_SECONDS', 1800),
        'provider_per_second' => (int) env('WHATSAPP_PROVIDER_CPS', 0),
        'client_per_minute' => (int) env('WHATSAPP_CLIENT_CPM', 0),
        'connector_per_minute' => (int) env('WHATSAPP_CONNECTOR_CPM', 0),
        'test_recipient_limit' => (int) env('WHATSAPP_TEST_RECIPIENT_LIMIT', 1),
    ],
];
