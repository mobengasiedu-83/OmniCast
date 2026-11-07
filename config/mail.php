<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        /*
        |----------------------------------------------------------------------
        | Email broadcast transport
        |----------------------------------------------------------------------
        |
        | Campaign email is submitted through this mailer, kept separate from the
        | default one so a bulk broadcast can point at a different mail server
        | than transactional mail. A relay that needs no authentication and no
        | TLS leaves the username, password and scheme empty.
        |
        | Leaving the scheme empty is not enough to keep an unencrypted relay
        | plaintext: Symfony still sends STARTTLS whenever the EHLO reply lists it,
        | which fails against a relay holding a self-signed certificate. The
        | `auto_tls` DSN option is the switch that stops that upgrade, so it is off
        | by default here and only switched on for a relay with a trusted
        | certificate.
        |
        */

        'broadcast' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_BROADCAST_SCHEME'),
            'auto_tls' => filter_var(env('MAIL_BROADCAST_AUTO_TLS', false), FILTER_VALIDATE_BOOL),
            'host' => env('MAIL_BROADCAST_HOST', '127.0.0.1'),
            'port' => (int) env('MAIL_BROADCAST_PORT', 2525),
            'username' => env('MAIL_BROADCAST_USERNAME'),
            'password' => env('MAIL_BROADCAST_PASSWORD'),
            'timeout' => (int) env('MAIL_BROADCAST_TIMEOUT', 15),
            'local_domain' => env('MAIL_BROADCAST_EHLO_DOMAIN', env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST))),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
    ],

];
