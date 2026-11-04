<?php

namespace App\Services\Voice;

use App\Enums\VoiceCallEventType;
use Carbon\CarbonInterface;

/**
 * One callback the switch posted for a call.
 *
 * The reference is the value OmniCast generated before originating the call, so
 * a callback can be matched to its call without relying on provider ids.
 */
final class VoiceCallEventPayload
{
    /**
     * @param  array<string, mixed>  $payload  the raw callback body, kept for audit
     */
    public function __construct(
        public readonly string $reference,
        public readonly VoiceCallEventType $type,
        public readonly ?string $digits = null,
        public readonly int $durationSeconds = 0,
        public readonly ?string $hangupCause = null,
        public readonly ?string $providerEventId = null,
        public readonly ?string $providerCallId = null,
        public readonly ?CarbonInterface $occurredAt = null,
        public readonly array $payload = [],
    ) {}
}
