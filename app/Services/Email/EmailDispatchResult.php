<?php

namespace App\Services\Email;

use App\Enums\EmailMessageStatus;

/**
 * What the transport did with a submitted email.
 *
 * `accepted` only means the mail server queued the message; whether it reached
 * the mailbox is decided later by the feedback that lands on the email webhook.
 */
final class EmailDispatchResult
{
    public function __construct(
        public readonly bool $accepted,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $message = null,
        public readonly EmailMessageStatus $status = EmailMessageStatus::Submitted,
    ) {}

    public static function accepted(?string $providerMessageId = null): self
    {
        return new self(true, $providerMessageId);
    }

    public static function rejected(string $message): self
    {
        return new self(false, null, $message, EmailMessageStatus::Failed);
    }
}
