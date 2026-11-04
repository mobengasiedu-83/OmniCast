<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

/**
 * Development SMS transport: writes the message to the log instead of hitting a network.
 *
 * The default driver, so a local checkout can run the SMS campaign flow end to
 * end without a provider account. Swap it with SMS_BROADCAST_DRIVER=txtconnect,
 * generic_json or kannel.
 */
class LogSmsGateway implements SmsGateway
{
    /**
     * Submit an SMS and report whether the provider accepted it.
     */
    public function send(OutboundSms $message): SmsDispatchResult
    {
        Log::channel('sms')->info('SMS accepted by the development transport.', [
            'reference' => $message->reference,
            'recipient' => $message->maskedTo(),
            'sender_id' => $message->from,
            'encoding' => $message->encoding->value,
            'segments' => $message->segments,
            'message_length' => mb_strlen($message->body),
        ]);

        return SmsDispatchResult::accepted('log-'.$message->reference);
    }

    /**
     * The transport name written onto each message row.
     */
    public function name(): string
    {
        return 'log';
    }
}
