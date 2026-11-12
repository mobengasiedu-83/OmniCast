<?php

namespace App\Data\WhatsApp;

use App\Enums\WhatsAppProviderErrorCategory;
use App\Enums\WhatsAppProviderResultStatus;

/**
 * Normalized provider response. Provider payloads are kept in memory only and
 * are recursively redacted before this object is returned.
 *
 * @param  array<string, mixed>  $data
 */
final readonly class WhatsAppProviderResponse
{
    public function __construct(
        public WhatsAppProviderResultStatus $status,
        public ?int $httpStatus,
        public ?string $providerReference,
        public ?WhatsAppProviderErrorCategory $errorCategory,
        public array $data = [],
    ) {}
}
