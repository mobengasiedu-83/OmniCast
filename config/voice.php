<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Voice broadcasting
    |--------------------------------------------------------------------------
    |
    | Voice campaigns are only switched on once the PBX dialplan, the audio and
    | the carrier have been through controlled UAT. The transport below is what
    | actually places the calls: `log` writes them to the application log,
    | `ami` originates them over the Asterisk Manager Interface and `ari` posts
    | them to the Asterisk REST Interface. Operators can override the driver at
    | runtime with the `voice.driver` platform setting.
    |
    */

    'driver' => env('VOICE_DRIVER', 'log'),

    'broadcast' => [
        'enabled' => filter_var(env('VOICE_BROADCAST_ENABLED', false), FILTER_VALIDATE_BOOL),
        'channels' => (int) env('VOICE_BROADCAST_GLOBAL_CHANNELS', 0),
        'cps' => (int) env('VOICE_BROADCAST_GLOBAL_CPS', 0),
        'media_mode' => env('VOICE_BROADCAST_MEDIA_MODE', 'pbx'),
        'media_base_url' => env('VOICE_BROADCAST_MEDIA_BASE_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Outbound caller ID
    |--------------------------------------------------------------------------
    |
    | Every voice campaign dials from the single platform DID. The runtime
    | platform setting `voice.outbound_caller_id` takes precedence over this
    | value; services.voice.outbound_caller_id is kept for backwards
    | compatibility with the readiness checks written in earlier phases.
    |
    */

    'outbound_caller_id' => env('VOICE_OUTBOUND_CALLER_ID'),

    /*
    |--------------------------------------------------------------------------
    | IVR defaults
    |--------------------------------------------------------------------------
    |
    | A campaign may play a DTMF menu before the message. The digit => action
    | map is a default: a campaign can override it per broadcast, and the
    | opt-out digit is always kept in the map so a recipient can always leave.
    |
    */

    'ivr' => [
        'timeout_seconds' => (int) env('VOICE_IVR_TIMEOUT_SECONDS', 6),
        'max_attempts' => (int) env('VOICE_IVR_MAX_ATTEMPTS', 3),
        'opt_out_digit' => (string) env('VOICE_IVR_OPT_OUT_DIGIT', '9'),
        'options' => [
            '1' => 'connect',
            '2' => 'repeat',
            '9' => 'opt_out',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Switch callbacks
    |--------------------------------------------------------------------------
    |
    | The PBX posts call events (ringing, answered, DTMF, hang-up) to
    | /webhooks/voice. The request body is signed with this shared secret so
    | only the switch can move a call through its lifecycle.
    |
    */

    'webhook' => [
        'secret' => env('VOICE_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Asterisk Manager Interface (VitalPBX)
    |--------------------------------------------------------------------------
    |
    | Use a dedicated AMI account with the minimum permissions needed to send
    | `Originate` actions. The trunk is the PJSIP endpoint ID, which may differ
    | from the VitalPBX display name.
    |
    */

    'ami' => [
        'host' => env('VITALPBX_AMI_HOST', '127.0.0.1'),
        'port' => (int) env('VITALPBX_AMI_PORT', 5038),
        'username' => env('VITALPBX_AMI_USERNAME'),
        'secret' => env('VITALPBX_AMI_SECRET'),
        'timeout' => (int) env('VITALPBX_AMI_TIMEOUT', 5),
        'trunk' => env('VITALPBX_TRUNK_NAME'),
        'context' => env('VITALPBX_BROADCAST_CONTEXT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Asterisk REST Interface
    |--------------------------------------------------------------------------
    |
    | ARI originates calls with a plain HTTP POST. Events arrive on the same
    | signed webhook, so only the originate call needs REST credentials.
    |
    */

    'ari' => [
        'url' => env('VITALPBX_ARI_URL', 'http://127.0.0.1:8088/ari'),
        'username' => env('VITALPBX_ARI_USERNAME'),
        'secret' => env('VITALPBX_ARI_SECRET'),
        'app' => env('VITALPBX_ARI_APP', 'omnicast'),
        'endpoint' => env('VITALPBX_ARI_ENDPOINT', 'PJSIP'),
        'timeout' => (int) env('VITALPBX_ARI_TIMEOUT', 10),
    ],

];
