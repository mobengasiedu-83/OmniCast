<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Kannel transport: the SMPP bind lives in Kannel, OmniCast posts to `sendsms`.
 *
 * A direct SMPP bind needs a long-lived socket and an asynchronous receipt
 * stream, which belongs in a worker; keeping the bind in Kannel means the
 * broadcast path stays a plain HTTP call while still going out over SMPP. Each
 * message carries its own signed `dlr-url`, so receipts come back to the SMS
 * webhook without any shared session state.
 */
class KannelSmsGateway implements SmsGateway
{
    /**
     * Submit an SMS through Kannel and report whether it was accepted.
     */
    public function send(OutboundSms $message): SmsDispatchResult
    {
        $url = (string) config('sms.kannel.url');

        if ($url === '') {
            Log::channel('sms')->error('SMS was not submitted because the Kannel send-sms URL is not configured.', [
                'reference' => $message->reference,
            ]);

            return SmsDispatchResult::rejected('The Kannel send-sms URL is not configured.');
        }

        $query = array_filter([
            'username' => (string) config('sms.kannel.username'),
            'password' => (string) config('sms.kannel.password'),
            'to' => $message->to,
            'from' => $message->from,
            'text' => $message->body,
            'charset' => $message->isUnicode() ? 'UTF-8' : 'GSM',
            'dlr-url' => $message->deliveryUrl(),
            'dlr-mask' => (string) max((int) config('sms.kannel.dlr_mask', 31), 1),
        ], fn (?string $value): bool => $value !== null && $value !== '');

        try {
            $response = Http::connectTimeout(3)
                ->timeout(15)
                ->get($url.'?'.http_build_query($query));
        } catch (ConnectionException $exception) {
            Log::channel('sms')->error('SMS provider connection failed.', [
                'reference' => $message->reference,
                'driver' => $this->name(),
                'exception' => $exception::class,
            ]);

            return SmsDispatchResult::rejected('The Kannel bearerbox could not be reached.');
        }

        // Kannel answers `0: Accepted for delivery`; every other code is a refusal.
        if (! $response->successful() || preg_match('/^0\s*:/', trim($response->body())) !== 1) {
            Log::channel('sms')->warning('Kannel rejected the message.', [
                'reference' => $message->reference,
                'status' => $response->status(),
                'response' => mb_substr(trim($response->body()), 0, 120),
            ]);

            return SmsDispatchResult::rejected('The Kannel bearerbox rejected the message.');
        }

        // Kannel returns no message id; receipts are matched on our reference.
        return SmsDispatchResult::accepted(null);
    }

    /**
     * The transport name written onto each message row.
     */
    public function name(): string
    {
        return 'kannel';
    }
}
