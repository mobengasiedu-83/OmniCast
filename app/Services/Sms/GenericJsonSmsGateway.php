<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Generic JSON transport for aggregators that are not TxtConnect.
 *
 * A single JSON POST carries the recipient, the sender, the body and the
 * reference the receipt webhook is signed with. Authentication is a bearer
 * token when one is configured, otherwise HTTP basic credentials.
 */
class GenericJsonSmsGateway implements SmsGateway
{
    /**
     * Submit an SMS and report whether the provider accepted it.
     */
    public function send(OutboundSms $message): SmsDispatchResult
    {
        $endpoint = (string) config('sms.generic_json.endpoint');
        $token = (string) config('sms.generic_json.token');
        $username = (string) config('sms.generic_json.username');
        $password = (string) config('sms.generic_json.password');

        if ($endpoint === '') {
            Log::channel('sms')->error('SMS was not submitted because the generic JSON endpoint is not configured.', [
                'reference' => $message->reference,
            ]);

            return SmsDispatchResult::rejected('The generic JSON endpoint is not configured.');
        }

        $request = Http::connectTimeout(3)->timeout(10)->acceptJson();

        if ($token !== '') {
            $request = $request->withToken($token);
        } elseif ($username !== '' && $password !== '') {
            $request = $request->withBasicAuth($username, $password);
        }

        try {
            $response = $request->post($endpoint, [
                'to' => $message->to,
                'from' => $message->from,
                'message' => $message->body,
                'reference' => $message->reference,
                'encoding' => $message->encoding->value,
                'segments' => $message->segments,
                'unicode' => $message->isUnicode(),
                'callback_url' => $message->deliveryUrl(),
            ]);
        } catch (ConnectionException $exception) {
            Log::channel('sms')->error('SMS provider connection failed.', [
                'reference' => $message->reference,
                'driver' => $this->name(),
                'exception' => $exception::class,
            ]);

            return SmsDispatchResult::rejected('The SMS gateway could not be reached.');
        }

        if (! $response->successful() || $response->json('success', true) === false) {
            Log::channel('sms')->warning('The SMS gateway rejected the message.', [
                'reference' => $message->reference,
                'status' => $response->status(),
            ]);

            return SmsDispatchResult::rejected('The SMS gateway rejected the message.');
        }

        $providerMessageId = $response->json('message_id')
            ?? $response->json('id')
            ?? $response->json('data.message_id')
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
        return 'generic_json';
    }
}
