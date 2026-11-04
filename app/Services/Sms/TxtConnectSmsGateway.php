<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use App\Services\PlatformConfiguration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * TxtConnect HTTP transport.
 *
 * TxtConnect answers every request with an envelope: `data.status_code` is
 * `000` only when the message was queued, and `data.in_error` flags a rejected
 * request even when HTTP itself succeeded.
 */
class TxtConnectSmsGateway implements SmsGateway
{
    public function __construct(protected PlatformConfiguration $configuration) {}

    /**
     * Submit an SMS and report whether TxtConnect accepted it.
     */
    public function send(OutboundSms $message): SmsDispatchResult
    {
        $endpoint = (string) $this->configuration->resolve('providers', 'sms.endpoint', config('sms.txtconnect.endpoint'));
        $token = (string) $this->configuration->resolve('providers', 'sms.token', config('sms.txtconnect.token'));

        if ($endpoint === '' || $token === '') {
            Log::channel('sms')->error('SMS was not submitted because the TxtConnect endpoint or token is not configured.', [
                'reference' => $message->reference,
            ]);

            return SmsDispatchResult::rejected('The TxtConnect endpoint or token is not configured.');
        }

        try {
            $response = Http::connectTimeout(3)
                ->timeout(10)
                ->withToken($token)
                ->acceptJson()
                ->post($endpoint, [
                    'to' => ltrim($message->to, '+'),
                    'from' => $message->from,
                    'unicode' => $message->isUnicode() ? '1' : '0',
                    'sms' => $message->body,
                ]);
        } catch (ConnectionException $exception) {
            Log::channel('sms')->error('SMS provider connection failed.', [
                'reference' => $message->reference,
                'driver' => $this->name(),
                'exception' => $exception::class,
            ]);

            return SmsDispatchResult::rejected('The TxtConnect gateway could not be reached.');
        }

        if (! $response->successful()
            || $response->json('data.status_code') !== '000'
            || $response->json('data.in_error') !== false) {
            Log::channel('sms')->warning('TxtConnect rejected the message.', [
                'reference' => $message->reference,
                'status' => $response->status(),
                'provider_code' => $response->json('data.status_code'),
            ]);

            return SmsDispatchResult::rejected('The TxtConnect gateway rejected the message.');
        }

        $providerMessageId = $response->json('data.message_id')
            ?? $response->json('data.uid')
            ?? $response->json('data.id');

        return SmsDispatchResult::accepted(
            is_string($providerMessageId) || is_numeric($providerMessageId) ? (string) $providerMessageId : null,
        );
    }

    /**
     * The transport name written onto each message row.
     */
    public function name(): string
    {
        return 'txtconnect';
    }
}
