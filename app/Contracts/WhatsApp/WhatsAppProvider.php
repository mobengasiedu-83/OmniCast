<?php

namespace App\Contracts\WhatsApp;

use App\Data\WhatsApp\WhatsAppProviderResponse;
use App\Models\WhatsAppProvider as WhatsAppProviderConfig;

/** Provider API boundary used by WhatsApp application services. */
interface WhatsAppProvider
{
    public function testConnection(WhatsAppProviderConfig $provider): WhatsAppProviderResponse;

    public function getConnectors(WhatsAppProviderConfig $provider): WhatsAppProviderResponse;

    public function getTemplates(
        WhatsAppProviderConfig $provider,
        string $wabaId,
        int $offset = 0,
        int $limit = 25,
        ?int $templateStatus = null,
    ): WhatsAppProviderResponse;

    /** @param array<string, mixed> $payload */
    public function send(WhatsAppProviderConfig $provider, array $payload): WhatsAppProviderResponse;
}
