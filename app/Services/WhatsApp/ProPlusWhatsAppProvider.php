<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\WhatsAppProvider as WhatsAppProviderContract;
use App\Data\WhatsApp\WhatsAppProviderResponse;
use App\Enums\WhatsAppProviderErrorCategory;
use App\Enums\WhatsAppProviderResultStatus;
use App\Models\WhatsAppProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** Pro Plus API boundary; response bodies are never written to logs. */
class ProPlusWhatsAppProvider implements WhatsAppProviderContract
{
    public function testConnection(WhatsAppProvider $provider): WhatsAppProviderResponse
    {
        return $this->getConnectors($provider);
    }

    public function getConnectors(WhatsAppProvider $provider): WhatsAppProviderResponse
    {
        return $this->get($provider, (string) config('whatsapp.provider.endpoints.connectors'));
    }

    public function getTemplate(WhatsAppProvider $provider, string $metaTemplateId): WhatsAppProviderResponse
    {
        return $this->get($provider, (string) config('whatsapp.provider.endpoints.template'), [
            'MetaTemplateId' => $metaTemplateId,
        ]);
    }

    public function getTemplates(
        WhatsAppProvider $provider,
        string $wabaId,
        int $offset = 0,
        int $limit = 25,
        ?int $templateStatus = null,
    ): WhatsAppProviderResponse {
        return $this->get($provider, (string) config('whatsapp.provider.endpoints.templates'), array_filter([
            'WABAID' => $wabaId,
            'offset' => max($offset, 0),
            'limit' => min(max($limit, 1), 25),
            'templateStatus' => $templateStatus,
        ], static fn (mixed $value): bool => $value !== null));
    }

    public function send(WhatsAppProvider $provider, array $payload): WhatsAppProviderResponse
    {
        if (! config('whatsapp.sending_enabled', false) || ! $provider->is_enabled) {
            return new WhatsAppProviderResponse(
                WhatsAppProviderResultStatus::Unknown,
                null,
                null,
                WhatsAppProviderErrorCategory::Unknown,
            );
        }

        return $this->request($provider, 'POST', (string) config('whatsapp.provider.endpoints.send'), $payload, true);
    }

    /** @param array<string, int|string> $query */
    protected function get(WhatsAppProvider $provider, string $path, array $query = []): WhatsAppProviderResponse
    {
        return $this->request($provider, 'GET', $path, $query);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function request(
        WhatsAppProvider $provider,
        string $method,
        string $path,
        array $payload = [],
        bool $isSubmission = false,
    ): WhatsAppProviderResponse {
        $clientId = config('whatsapp.provider.client_id');
        $apiKey = config('whatsapp.provider.api_key');
        $baseUrl = config('whatsapp.provider.base_url');

        if (! is_string($baseUrl) || trim($baseUrl) === ''
            || ! is_string($clientId) || trim($clientId) === ''
            || ! is_string($apiKey) || trim($apiKey) === '') {
            return new WhatsAppProviderResponse(
                WhatsAppProviderResultStatus::Unknown,
                null,
                null,
                WhatsAppProviderErrorCategory::Authentication,
            );
        }

        try {
            $request = $this->requestFor($provider, $baseUrl, $clientId, $apiKey);
            $response = $method === 'GET'
                ? $request->get($path, $payload)
                : $request->post($path, $payload);

            return $this->normalize($response, $isSubmission);
        } catch (ConnectionException) {
            return new WhatsAppProviderResponse(
                WhatsAppProviderResultStatus::Unknown,
                null,
                null,
                WhatsAppProviderErrorCategory::Transport,
            );
        }
    }

    protected function requestFor(WhatsAppProvider $provider, string $baseUrl, string $clientId, string $apiKey): PendingRequest
    {
        $requestId = request()->attributes->get('request_id');

        return Http::baseUrl(rtrim($baseUrl, '/'))
            ->withBasicAuth($clientId, $apiKey)
            ->acceptJson()
            ->connectTimeout($provider->connect_timeout)
            ->timeout($provider->request_timeout)
            ->withHeaders([
                'X-Request-Id' => is_string($requestId) ? $requestId : (string) Str::uuid(),
            ]);
    }

    protected function normalize(Response $response, bool $isSubmission): WhatsAppProviderResponse
    {
        $body = $response->json();
        $providerReference = is_array($body) ? $this->providerReference($body) : null;
        $data = is_array($body) ? $this->redact($body) : [];
        $httpStatus = $response->status();
        $successful = $response->successful();
        $applicationError = is_array($body) && $this->hasApplicationError($body);

        $status = match (true) {
            ! $successful && $httpStatus >= 400 && $httpStatus < 500 && $httpStatus !== 429 => WhatsAppProviderResultStatus::Rejected,
            $applicationError => WhatsAppProviderResultStatus::Rejected,
            $successful && (! $isSubmission || $providerReference !== null) => WhatsAppProviderResultStatus::Accepted,
            default => WhatsAppProviderResultStatus::Unknown,
        };

        return new WhatsAppProviderResponse(
            $status,
            $httpStatus,
            $providerReference,
            $this->errorCategory($httpStatus),
            $data,
        );
    }

    /** @param array<string,mixed> $body */
    protected function hasApplicationError(array $body): bool
    {
        foreach (['statusCode', 'errorCode'] as $key) {
            if (array_key_exists($key, $body) && is_numeric($body[$key]) && (int) $body[$key] !== 0) {
                return true;
            }
        }

        return isset($body['status']) && is_string($body['status'])
            && in_array(strtolower($body['status']), ['error', 'failed', 'failure', 'rejected'], true);
    }

    /** @param array<string, mixed> $payload */
    protected function providerReference(array $payload): ?string
    {
        foreach (['MessageId', 'messageId', 'message_id', 'id'] as $key) {
            if (isset($payload[$key]) && is_scalar($payload[$key]) && (string) $payload[$key] !== '') {
                return (string) $payload[$key];
            }
        }

        if (isset($payload['messages']) && is_array($payload['messages'])) {
            foreach ($payload['messages'] as $message) {
                if (is_array($message)) {
                    $reference = $this->providerReference($message);

                    if ($reference !== null) {
                        return $reference;
                    }
                }
            }
        }

        return null;
    }

    protected function errorCategory(int $status): ?WhatsAppProviderErrorCategory
    {
        return match (true) {
            $status === 401 || $status === 403 => WhatsAppProviderErrorCategory::Authentication,
            $status === 400 || $status === 422 => WhatsAppProviderErrorCategory::Validation,
            $status === 429 => WhatsAppProviderErrorCategory::RateLimited,
            $status >= 500 => WhatsAppProviderErrorCategory::Provider,
            $status >= 400 => WhatsAppProviderErrorCategory::Unknown,
            default => null,
        };
    }

    /** @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    protected function redact(array $value): array
    {
        $redacted = [];

        foreach ($value as $key => $item) {
            if (preg_match('/token|secret|password|credential|authorization|api.?key|client.?id|otp|one.?time.?password/i', (string) $key) === 1) {
                $redacted[$key] = '[REDACTED]';

                continue;
            }

            $redacted[$key] = is_array($item) ? $this->redact($item) : $item;
        }

        return $redacted;
    }
}
