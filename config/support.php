<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Support desk
    |--------------------------------------------------------------------------
    |
    | The public contact form and the client ticket screens both write into the
    | support register. Attachments stay on a private disk and are only ever
    | streamed through the ticket's policy, never from a public URL.
    |
    */

    'attachments' => [
        'disk' => env('SUPPORT_ATTACHMENT_DISK', 'local'),
        'max_files' => 3,
        'max_kilobytes' => 5120,
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'csv', 'txt', 'doc', 'docx', 'xls', 'xlsx', 'zip'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Abuse protection
    |--------------------------------------------------------------------------
    |
    | The public form is the only screen an anonymous visitor can write through,
    | so it is limited per address on top of the Turnstile challenge.
    |
    */

    'rate_limits' => [
        'public_enquiries_per_minute' => (int) env('SUPPORT_ENQUIRY_RATE_LIMIT', 5),
        'ticket_replies_per_minute' => (int) env('SUPPORT_REPLY_RATE_LIMIT', 20),
    ],

];
