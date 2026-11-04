<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS Sender ID
    |--------------------------------------------------------------------------
    |
    | Alphanumeric SMS sender IDs are shown on the recipient handset, so the
    | length limit counts spaces. The name column compares case-sensitively:
    | "OmniCast" and "omnicast" are two different sender IDs on one account.
    |
    */

    'sms' => [
        'min_length' => (int) env('SMS_SENDER_ID_MIN_LENGTH', 3),
        'max_length' => (int) env('SMS_SENDER_ID_MAX_LENGTH', 11),
        'allow_spaces' => filter_var(env('SMS_SENDER_ID_ALLOW_SPACES', true), FILTER_VALIDATE_BOOL),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Sender Address
    |--------------------------------------------------------------------------
    |
    | Email campaigns deliver as an approved from address. The address is
    | lower-cased before it is stored, and the sender_ids.name column holds
    | 120 characters, so that is where this limit is capped.
    |
    */

    'email' => [
        'max_length' => (int) env('EMAIL_SENDER_ID_MAX_LENGTH', 120),
    ],

];
