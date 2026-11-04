<?php

namespace App\Services\Sms;

use App\Enums\SmsMessageStatus;

/**
 * What the provider did with a submitted message.
 *
 * `accepted` only means the provider queued the message; whether it reached the
 * handset is decided later by the receipts that land on the SMS webhook.
 */
final class SmsDispatchResult
{
    public function __construct(
        public readonly bool $accepted,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $message = null,
        public readonly SmsMessageStatus $status = SmsMessageStatus::Submitted,
    ) {}

    public static function accepted(?string $providerMessageId = null): self
    {
        return new self(true, $providerMessageId);
    }

    public static function rejected(string $message): self
    {
        return new self(false, null, $message, SmsMessageStatus::Failed);
    }
}
