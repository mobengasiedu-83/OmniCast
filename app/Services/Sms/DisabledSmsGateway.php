<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;

/**
 * Transport used when SMS broadcasting is switched off.
 *
 * The provider is never contacted: every recipient is failed with the reason
 * the operator can act on, instead of the campaign silently reporting success.
 */
class DisabledSmsGateway implements SmsGateway
{
    public function __construct(protected string $reason) {}

    /**
     * Refuse the message without contacting a provider.
     */
    public function send(OutboundSms $message): SmsDispatchResult
    {
        return SmsDispatchResult::rejected($this->reason);
    }

    /**
     * The transport name written onto each message row.
     */
    public function name(): string
    {
        return 'disabled';
    }
}
