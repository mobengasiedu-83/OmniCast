<?php

namespace App\Services\Voice;

/**
 * What the switch did with an originate request.
 *
 * `accepted` only means the switch queued the call; whether it was answered is
 * decided later by the callbacks that land on the voice webhook.
 */
final class VoiceDispatchResult
{
    public function __construct(
        public readonly bool $accepted,
        public readonly ?string $providerCallId = null,
        public readonly ?string $message = null,
    ) {}

    public static function accepted(?string $providerCallId = null): self
    {
        return new self(true, $providerCallId);
    }

    public static function rejected(string $message): self
    {
        return new self(false, null, $message);
    }
}
