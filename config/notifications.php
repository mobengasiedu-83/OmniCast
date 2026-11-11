<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notification centre
    |--------------------------------------------------------------------------
    |
    | The centre is in-app first: a notification is written to the user's inbox
    | and only leaves the platform by email when the account has opted into the
    | category's email channel. `enabled` is the kill switch a rollback flips to
    | stop writing new notifications without touching the inbox already written.
    |
    */

    'enabled' => filter_var(env('NOTIFICATIONS_ENABLED', true), FILTER_VALIDATE_BOOL),

    'queue' => env('NOTIFICATIONS_QUEUE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Channels and defaults
    |--------------------------------------------------------------------------
    |
    | Each category decides what a brand new account receives. In-app is on for
    | everything because it is the centre itself; email is on only where the
    | message is a receipt the account is expected to keep (billing) or an
    | onboarding decision that may be acted on days later (compliance).
    |
    */

    'channels' => ['in_app', 'email', 'sms'],

    'preferences' => [
        'campaigns' => ['in_app' => true, 'email' => false, 'sms' => false],
        'automations' => ['in_app' => true, 'email' => false, 'sms' => false],
        'billing' => ['in_app' => true, 'email' => true, 'sms' => false],
        'compliance' => ['in_app' => true, 'email' => true, 'sms' => false],
        'system' => ['in_app' => true, 'email' => false, 'sms' => false],
        'platform' => ['in_app' => true, 'email' => false, 'sms' => false],
    ],

    /*
    |--------------------------------------------------------------------------
    | The SMS copy
    |--------------------------------------------------------------------------
    |
    | SMS is opt-in twice: the account has to tick the category's SMS channel
    | and the platform has to switch `enabled` on, because every part is billed.
    | `sender` is the alphanumeric sender the short copy is submitted with, and
    | `max_length` caps the composed body (title, body and action link) before it
    | is truncated, so one loud announcement cannot become a five-part message.
    |
    */

    'sms' => [
        'enabled' => filter_var(env('NOTIFICATIONS_SMS_ENABLED', false), FILTER_VALIDATE_BOOL),
        'sender' => env('NOTIFICATIONS_SMS_SENDER', 'OMNICAST'),
        'max_length' => (int) env('NOTIFICATIONS_SMS_MAX_LENGTH', 320),
    ],

    /*
    |--------------------------------------------------------------------------
    | Announcement delivery
    |--------------------------------------------------------------------------
    |
    | Recipients are resolved and written in chunks so an announcement to every
    | tenant does not hold one worker slot for the whole fan-out. A recipient
    | who switched the platform category off still keeps the in-app row: an
    | announcement is addressed to the account rather than to a topic.
    |
    */

    'announcements' => [
        'chunk' => (int) env('NOTIFICATIONS_ANNOUNCEMENT_CHUNK', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Inbox size and retention
    |--------------------------------------------------------------------------
    |
    | The bell reads `bell_items` and the inbox pages by `per_page`. These
    | windows may extend a legally approved retention period; pruning remains
    | disabled until an approval is recorded in Admin > Data retention.
    |
    */

    'inbox' => [
        'per_page' => (int) env('NOTIFICATIONS_PER_PAGE', 20),
        'bell_items' => (int) env('NOTIFICATIONS_BELL_ITEMS', 6),
    ],

    'retention' => [
        'read_days' => (int) env('NOTIFICATIONS_READ_RETENTION_DAYS', 90),
        'sent_days' => (int) env('NOTIFICATIONS_SENT_RETENTION_DAYS', 365),
    ],

];
