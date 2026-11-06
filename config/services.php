<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'sms' => [
        'driver' => env('SMS_DRIVER', 'txtconnect'),
        'endpoint' => env('SMS_ENDPOINT', 'https://api.txtconnect.net/dev/api/sms/send'),
        'token' => env('SMS_TOKEN'),
        'sender_id' => env('SMS_SENDER_ID', 'OmniCast'),
    ],

    'voice' => [
        /*
        | Every voice campaign dials from the single platform DID (outbound
        | caller ID). Operators can override it at runtime with the
        | `voice.outbound_caller_id` platform setting.
        */
        'outbound_caller_id' => env('VOICE_OUTBOUND_CALLER_ID'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Cloudflare Turnstile guards the sign-in form. The check is only armed when
    | TURNSTILE_ENABLED is on and both keys are present, so local and test runs
    | keep the plain form and production turns on the challenge.
    */
    'turnstile' => [
        'enabled' => filter_var(env('TURNSTILE_ENABLED', false), FILTER_VALIDATE_BOOL),
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'verify_url' => env('TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
        'timeout' => (int) env('TURNSTILE_TIMEOUT', 10),
    ],

];
