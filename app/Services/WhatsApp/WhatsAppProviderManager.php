<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\WhatsAppProvider as WhatsAppProviderContract;
use App\Data\WhatsApp\WhatsAppProviderResponse;
use App\Models\WhatsAppProvider;

class WhatsAppProviderManager implements WhatsAppProviderContract
{
    public function __construct(
        private ProPlusWhatsAppProvider $proPlus,
        private MetaCloudWhatsAppProvider $metaCloud,
    ) {}

    public function testConnection(WhatsAppProvider $provider): WhatsAppProviderResponse
    {
        return $this->driver($provider)->testConnection($provider);
    }

    public function getConnectors(WhatsAppProvider $provider): WhatsAppProviderResponse
    {
        return $this->driver($provider)->getConnectors($provider);
    }

    public function getTemplate(WhatsAppProvider $provider, string $metaTemplateId): WhatsAppProviderResponse
    {
        return $this->driver($provider)->getTemplate($provider, $metaTemplateId);
    }

    public function getTemplates(WhatsAppProvider $provider, string $wabaId, int $offset = 0, int $limit = 25, ?int $templateStatus = null): WhatsAppProviderResponse
    {
        return $this->driver($provider)->getTemplates($provider, $wabaId, $offset, $limit, $templateStatus);
    }

    public function send(WhatsAppProvider $provider, array $payload): WhatsAppProviderResponse
    {
        return $this->driver($provider)->send($provider, $payload);
    }

    public function sendBatch(WhatsAppProvider $provider, array $payload): WhatsAppProviderResponse
    {
        return $this->driver($provider)->sendBatch($provider, $payload);
    }

    private function driver(WhatsAppProvider $provider): WhatsAppProviderContract
    {
        return match ($provider->driver) {
            'meta_cloud' => $this->metaCloud,
            default => $this->proPlus,
        };
    }
}
