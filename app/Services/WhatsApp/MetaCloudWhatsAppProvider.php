<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\WhatsAppProvider as WhatsAppProviderContract;
use App\Data\WhatsApp\WhatsAppProviderResponse;
use App\Enums\WhatsAppProviderErrorCategory;
use App\Enums\WhatsAppProviderResultStatus;
use App\Models\WhatsAppConnector;
use App\Models\WhatsAppProvider;
use App\Models\WhatsAppTemplate;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class MetaCloudWhatsAppProvider implements WhatsAppProviderContract
{
    public function testConnection(WhatsAppProvider $provider): WhatsAppProviderResponse
    {
        $connector = $provider->connectors()->whereNotNull('access_token')->first();
        if ($connector === null) {
            return $this->failure(null, WhatsAppProviderErrorCategory::Authentication);
        }

        try {
            $response = $this->request($connector)->get("/{$this->version()}/{$connector->external_id}", ['fields' => 'id']);
        } catch (ConnectionException) {
            return $this->failure(null, WhatsAppProviderErrorCategory::Transport);
        }

        return new WhatsAppProviderResponse(
            $response->successful() && (string) $response->json('id') === $connector->external_id
                ? WhatsAppProviderResultStatus::Accepted
                : WhatsAppProviderResultStatus::Rejected,
            $response->status(),
            null,
            $this->errorCategory($response),
            $this->redact((array) $response->json()),
        );
    }

    public function getConnectors(WhatsAppProvider $provider): WhatsAppProviderResponse
    {
        $connectors = $provider->connectors()->get();

        return new WhatsAppProviderResponse(WhatsAppProviderResultStatus::Accepted, 200, null, null, [
            'data' => $connectors->map(fn (WhatsAppConnector $connector): array => [
                'phoneNumberId' => $connector->external_id,
                'phoneNumber' => $connector->sender_number,
                'connectorName' => $connector->connector_name,
                'wabaId' => $connector->waba_id,
            ])->all(),
        ]);
    }

    public function getTemplate(WhatsAppProvider $provider, string $metaTemplateId): WhatsAppProviderResponse
    {
        $template = WhatsAppTemplate::query()->where('meta_template_id', $metaTemplateId)
            ->whereHas('connector', fn ($query) => $query->where('provider_id', $provider->id)->whereNotNull('access_token'))
            ->with('connector')->first();
        if ($template === null) {
            return $this->failure(404, WhatsAppProviderErrorCategory::Validation);
        }

        try {
            $response = $this->request($template->connector)->get("/{$this->version()}/{$metaTemplateId}", [
                'fields' => 'id,name,language,category,status,components',
            ]);
        } catch (ConnectionException) {
            return $this->failure(null, WhatsAppProviderErrorCategory::Transport);
        }

        return $this->normalize($response);
    }

    public function getTemplates(WhatsAppProvider $provider, string $wabaId, int $offset = 0, int $limit = 25, ?int $templateStatus = null): WhatsAppProviderResponse
    {
        $connector = $provider->connectors()->where('waba_id', $wabaId)->whereNotNull('access_token')->first();
        if ($connector === null) {
            return $this->failure(404, WhatsAppProviderErrorCategory::Validation);
        }
        try {
            $response = $this->request($connector)->get("/{$this->version()}/{$wabaId}/message_templates", [
                'fields' => 'id,name,language,category,status,components',
                'limit' => min(max($limit, 1), 100),
            ]);
        } catch (ConnectionException) {
            return $this->failure(null, WhatsAppProviderErrorCategory::Transport);
        }

        return $this->normalize($response);
    }

    public function send(WhatsAppProvider $provider, array $payload): WhatsAppProviderResponse
    {
        return $this->sendBatch($provider, $payload);
    }

    public function sendBatch(WhatsAppProvider $provider, array $payload): WhatsAppProviderResponse
    {
        if (! config('whatsapp.sending_enabled', false) || ! $provider->is_enabled) {
            return $this->failure(null, WhatsAppProviderErrorCategory::Authentication);
        }
        $senderNumber = (string) ($payload['SenderNumber'] ?? '');
        $connector = $provider->connectors()->where('sender_number', $senderNumber)->whereNotNull('access_token')->first();
        if ($connector === null || $connector->token_expires_at?->isPast()) {
            return $this->failure(401, WhatsAppProviderErrorCategory::Authentication);
        }
        $template = WhatsAppTemplate::query()->where('connector_id', $connector->id)
            ->where('meta_template_id', (string) ($payload['MetaTemplateId'] ?? ''))->first();
        if ($template === null || ! $template->isApproved()) {
            return $this->failure(400, WhatsAppProviderErrorCategory::Validation);
        }
        $recipients = isset($payload['BulkPayload']) ? [$payload['BulkPayload']] : ($payload['CustomPayload'] ?? []);
        if (! is_array($recipients) || $recipients === []) {
            return $this->failure(400, WhatsAppProviderErrorCategory::Validation);
        }
        if (count($recipients) !== 1) {
            return $this->failure(422, WhatsAppProviderErrorCategory::Validation);
        }

        $messageReferences = [];
        $lastResponse = null;
        foreach ($recipients as $recipient) {
            if (! is_array($recipient) || empty($recipient['RecieverNumber'])) {
                return $this->failure(400, WhatsAppProviderErrorCategory::Validation);
            }
            $graphPayload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => ltrim((string) $recipient['RecieverNumber'], '+'),
                'type' => 'template',
                'template' => [
                    'name' => $template->name,
                    'language' => ['code' => $template->language],
                    'components' => $this->templateComponents($template, $recipient, $payload),
                ],
            ];
            try {
                $lastResponse = $this->request($connector)->post("/{$this->version()}/{$connector->external_id}/messages", $graphPayload);
            } catch (ConnectionException) {
                return $this->failure(null, WhatsAppProviderErrorCategory::Transport);
            }
            if (! $lastResponse->successful()) {
                return $this->normalize($lastResponse);
            }
            $messageId = $lastResponse->json('messages.0.id');
            if (! is_string($messageId) || $messageId === '') {
                return $this->normalize($lastResponse, true);
            }
            $messageReferences[(string) $recipient['RecieverNumber']] = $messageId;
        }

        return new WhatsAppProviderResponse(
            WhatsAppProviderResultStatus::Accepted,
            $lastResponse?->status(),
            array_values($messageReferences)[0] ?? null,
            null,
            $this->redact((array) $lastResponse?->json()),
            $messageReferences,
        );
    }

    /** @param array<string, mixed> $recipient @param array<string, mixed> $payload @return list<array<string, mixed>> */
    private function templateComponents(WhatsAppTemplate $template, array $recipient, array $payload): array
    {
        $components = [];
        foreach ((array) $template->components as $component) {
            if (! is_array($component)) {
                continue;
            }
            $type = strtolower((string) ($component['type'] ?? ''));
            if ($type === 'body' && is_array($recipient['BodyVariables'] ?? null) && $recipient['BodyVariables'] !== []) {
                $components[] = [
                    'type' => 'body',
                    'parameters' => array_map(static fn (mixed $value): array => ['type' => 'text', 'text' => (string) $value], array_values($recipient['BodyVariables'])),
                ];
            }
            if ($type === 'header') {
                $format = strtolower((string) ($component['format'] ?? 'text'));
                if ($format === 'text' && filled($recipient['HeaderVariable'] ?? null)) {
                    $components[] = ['type' => 'header', 'parameters' => [['type' => 'text', 'text' => (string) $recipient['HeaderVariable']]]];
                } elseif (in_array($format, ['image', 'video', 'document'], true) && filled($payload['MediaFilePath'] ?? null)) {
                    $media = ['link' => (string) $payload['MediaFilePath']];
                    if ($format === 'document' && filled($payload['FileName'] ?? null)) {
                        $media['filename'] = (string) $payload['FileName'];
                    }
                    $components[] = ['type' => 'header', 'parameters' => [['type' => $format, $format => $media]]];
                }
            }
        }

        return $components;
    }

    private function request(WhatsAppConnector $connector): PendingRequest
    {
        return Http::baseUrl('https://graph.facebook.com')->withToken((string) $connector->access_token)
            ->acceptJson()->connectTimeout(5)->timeout(20);
    }

    private function version(): string
    {
        return (string) config('whatsapp.meta.graph_version', 'v26.0');
    }

    private function normalize(Response $response, bool $isSubmission = false): WhatsAppProviderResponse
    {
        $data = (array) $response->json();
        $messageId = $data['messages'][0]['id'] ?? null;
        $accepted = $response->successful() && (! $isSubmission || is_string($messageId));

        return new WhatsAppProviderResponse(
            $accepted ? WhatsAppProviderResultStatus::Accepted : ($response->clientError() ? WhatsAppProviderResultStatus::Rejected : WhatsAppProviderResultStatus::Unknown),
            $response->status(),
            is_string($messageId) ? $messageId : null,
            $this->errorCategory($response),
            $this->redact($data),
        );
    }

    private function errorCategory(Response $response): ?WhatsAppProviderErrorCategory
    {
        return match (true) {
            $response->status() === 401 || $response->status() === 403 => WhatsAppProviderErrorCategory::Authentication,
            $response->status() === 400 || $response->status() === 422 => WhatsAppProviderErrorCategory::Validation,
            $response->status() === 429 => WhatsAppProviderErrorCategory::RateLimited,
            $response->serverError() => WhatsAppProviderErrorCategory::Provider,
            $response->failed() => WhatsAppProviderErrorCategory::Unknown,
            default => null,
        };
    }

    private function failure(?int $httpStatus, WhatsAppProviderErrorCategory $category): WhatsAppProviderResponse
    {
        $status = $category === WhatsAppProviderErrorCategory::Validation || $category === WhatsAppProviderErrorCategory::Authentication
            ? WhatsAppProviderResultStatus::Rejected
            : WhatsAppProviderResultStatus::Unknown;

        return new WhatsAppProviderResponse($status, $httpStatus, null, $category);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function redact(array $data): array
    {
        $safe = [];
        foreach ($data as $key => $value) {
            $safe[$key] = preg_match('/token|secret|password|authorization|credential/i', (string) $key) === 1
                ? '[REDACTED]'
                : (is_array($value) ? $this->redact($value) : $value);
        }

        return $safe;
    }
}
