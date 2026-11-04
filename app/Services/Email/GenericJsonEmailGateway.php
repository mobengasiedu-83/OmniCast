<?php

namespace App\Services\Email;

use App\Contracts\EmailGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Generic JSON transport for HTTP email providers without a dedicated driver.
 *
 * A single JSON POST carries the recipient, the sender, the subject, both bodies
 * and the reference the receipt webhook is signed with. Authentication is a
 * bearer token when one is configured, otherwise HTTP basic credentials.
 */
class GenericJsonEmailGateway implements EmailGateway
{
    /**
     * Submit an email and report whether the provider accepted it.
     */
    public function send(OutboundEmail $email): EmailDispatchResult
    {
        $endpoint = (string) config('email.generic_json.endpoint');
        $token = (string) config('email.generic_json.token');
        $username = (string) config('email.generic_json.username');
        $password = (string) config('email.generic_json.password');

        if ($endpoint === '') {
            Log::channel('email')->error('Email was not submitted because the generic JSON endpoint is not configured.', [
                'reference' => $email->reference,
            ]);

            return EmailDispatchResult::rejected('The generic JSON endpoint is not configured.');
        }

        $request = Http::connectTimeout(3)->timeout(15)->acceptJson();

        if ($token !== '') {
            $request = $request->withToken($token);
        } elseif ($username !== '' && $password !== '') {
            $request = $request->withBasicAuth($username, $password);
        }

        try {
            $response = $request->post($endpoint, [
                'to' => $email->to,
                'from' => $email->from,
                'from_name' => $email->fromName,
                'subject' => $email->subject,
                'html' => $email->htmlBody(),
                'text' => $email->textBody(),
                'format' => $email->format->value,
                'reference' => $email->reference,
                'callback_url' => $email->deliveryUrl(),
            ]);
        } catch (ConnectionException $exception) {
            Log::channel('email')->error('Email provider connection failed.', [
                'reference' => $email->reference,
                'driver' => $this->name(),
                'exception' => $exception::class,
            ]);

            return EmailDispatchResult::rejected('The email gateway could not be reached.');
        }

        if (! $response->successful() || $response->json('success', true) === false) {
            Log::channel('email')->warning('The email gateway rejected the message.', [
                'reference' => $email->reference,
                'status' => $response->status(),
            ]);

            return EmailDispatchResult::rejected('The email gateway rejected the message.');
        }

        $providerMessageId = $response->json('message_id')
            ?? $response->json('id')
            ?? $response->json('data.message_id')
            ?? $response->json('data.id');

        return EmailDispatchResult::accepted(
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
