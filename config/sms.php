<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS broadcasting
    |--------------------------------------------------------------------------
    |
    | `log` writes every message to storage/logs/sms.log and is the default, so a
    | local checkout can run the campaign flow without a provider account.
    | `txtconnect`, `generic_json` and `kannel` are live transports: Kannel holds
    | the SMPP bind, so the broadcast path stays a plain HTTP call. Operators can
    | override the driver at runtime with the `sms.driver` platform setting.
    |
    */

    'driver' => env('SMS_BROADCAST_DRIVER', 'log'),

    'broadcast' => [
        'enabled' => filter_var(env('SMS_BROADCAST_ENABLED', false), FILTER_VALIDATE_BOOL),
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery receipts
    |--------------------------------------------------------------------------
    |
    | The provider posts a receipt per message to /webhooks/sms. A POST body is
    | signed with this shared secret in X-Omnicast-Signature; a GET receipt
    | (Kannel's dlr-url) carries the same signature for its reference in `sig`.
    |
    */

    'webhook' => [
        'secret' => env('SMS_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | TxtConnect
    |--------------------------------------------------------------------------
    |
    | The same account the OTP flow uses. The endpoint and token can also be set
    | at runtime through the `providers` settings group.
    |
    */

    'txtconnect' => [
        'endpoint' => env('SMS_ENDPOINT', 'https://api.txtconnect.net/dev/api/sms/send'),
        'token' => env('SMS_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Generic JSON
    |--------------------------------------------------------------------------
    |
    | For aggregators without a dedicated driver: one JSON POST per message with
    | a bearer token or HTTP basic credentials.
    |
    */

    'generic_json' => [
        'endpoint' => env('SMS_GENERIC_ENDPOINT'),
        'token' => env('SMS_GENERIC_TOKEN'),
        'username' => env('SMS_GENERIC_USERNAME'),
        'password' => env('SMS_GENERIC_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Kannel (SMPP)
    |--------------------------------------------------------------------------
    |
    | The `sendsms` URL of the Kannel bearerbox. Each message carries a signed
    | `dlr-url`, and the mask asks for the delivery receipts the report needs
    | (31 = all receipt types).
    |
    */

    'kannel' => [
        'url' => env('SMS_KANNEL_URL'),
        'username' => env('SMS_KANNEL_USERNAME'),
        'password' => env('SMS_KANNEL_PASSWORD'),
        'dlr_mask' => (int) env('SMS_KANNEL_DLR_MASK', 31),
    ],

];
