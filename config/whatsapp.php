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
];
