<?php

namespace App\Contracts;

use App\Services\Sms\OutboundSms;
use App\Services\Sms\SmsDispatchResult;

/**
 * The outbound SMS transport used when a campaign is dispatched.
 *
 * A transport only reports whether the provider accepted the message; delivery
 * is confirmed later through the signed receipt webhook.
 */
interface SmsGateway
{
    /**
     * Submit an SMS and report what the provider did with it.
     */
    public function send(OutboundSms $message): SmsDispatchResult;

    /**
     * The transport name written onto each message row.
     */
    public function name(): string;
}
