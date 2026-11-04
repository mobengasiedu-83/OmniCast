<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Campaign Approval
    |--------------------------------------------------------------------------
    |
    | A campaign only ever needs a reviewer when this is switched on. Left off,
    | a campaign becomes runnable as soon as the assets it delivers under are
    | approved: the sender ID for SMS and email, the audio for voice.
    |
    */

    'require_approval' => filter_var(env('CAMPAIGNS_REQUIRE_APPROVAL', false), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Asset Gates
    |--------------------------------------------------------------------------
    |
    | Each gate below is what actually stands between a campaign and delivery.
    | Turn a gate off only when the platform has another way of vetting the
    | asset, because campaigns then run without that approval.
    |
    */

    'require_approved_sender_id' => filter_var(env('CAMPAIGNS_REQUIRE_APPROVED_SENDER_ID', true), FILTER_VALIDATE_BOOL),

    'require_approved_audio' => filter_var(env('CAMPAIGNS_REQUIRE_APPROVED_AUDIO', true), FILTER_VALIDATE_BOOL),

];
