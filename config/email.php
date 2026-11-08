<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Email broadcasting
    |--------------------------------------------------------------------------
    |
    | `log` writes every message to storage/logs/email.log and is the default, so
    | a local checkout can run the campaign flow without a mail server.
    | `smtp` submits the message through the `broadcast` mailer in config/mail.php
    | and `generic_json` posts it to an HTTP provider. Operators can override the
    | driver at runtime with the `email.driver` platform setting.
    |
    */

    'driver' => env('EMAIL_BROADCAST_DRIVER', 'log'),

    'broadcast' => [
        'enabled' => filter_var(env('EMAIL_BROADCAST_ENABLED', false), FILTER_VALIDATE_BOOL),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default sender
    |--------------------------------------------------------------------------
    |
    | Used when a campaign has no approved email sender ID of its own. The
    | envelope address is what bounces come back to, so it must be a mailbox the
    | platform actually reads.
    |
    */

    'from' => [
        'address' => env('MAIL_BROADCAST_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', 'noreply@sysup360.com')),
        'name' => env('MAIL_BROADCAST_FROM_NAME', env('MAIL_FROM_NAME', env('APP_NAME', 'SysUp360 OmniCast'))),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMTP transport
    |--------------------------------------------------------------------------
    |
    | The `smtp` driver submits through a Laravel mailer rather than opening the
    | socket itself, so the host, port, credentials and TLS setting live in
    | config/mail.php under the `broadcast` mailer (MAIL_BROADCAST_*). This key
    | only names the mailer to use.
    |
    */

    'mailer' => env('EMAIL_BROADCAST_MAILER', 'broadcast'),

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    |
    | Files a campaign delivers with its body. They stay on a private disk: the
    | transport reads them when the message is submitted, and the reviewer
    | downloads them through the application, never by a public link.
    |
    */

    'attachments' => [
        'disk' => env('EMAIL_ATTACHMENT_DISK', 'local'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Inline images
    |--------------------------------------------------------------------------
    |
    | An image placed in the body is referenced by URL, so it has to sit on a
    | publicly readable disk that `storage:link` exposes. Point this at a bucket
    | or CDN in a deployment that serves media that way.
    |
    */

    'images' => [
        'disk' => env('EMAIL_IMAGE_DISK', 'public'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery feedback
    |--------------------------------------------------------------------------
    |
    | The mail server posts delivery, bounce and complaint feedback per message
    | to /webhooks/email. A POST body is signed with this shared secret in
    | X-Omnicast-Signature; a querystring receipt signs its reference in `sig`.
    |
    */

    'webhook' => [
        'secret' => env('EMAIL_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Generic JSON
    |--------------------------------------------------------------------------
    |
    | For HTTP providers without a dedicated driver: one JSON POST per message
    | with a bearer token or HTTP basic credentials.
    |
    */

    'generic_json' => [
        'endpoint' => env('EMAIL_GENERIC_ENDPOINT'),
        'token' => env('EMAIL_GENERIC_TOKEN'),
        'username' => env('EMAIL_GENERIC_USERNAME'),
        'password' => env('EMAIL_GENERIC_PASSWORD'),
    ],

];
