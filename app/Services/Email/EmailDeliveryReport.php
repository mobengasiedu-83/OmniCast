<?php

namespace App\Services\Email;

use App\Enums\EmailMessageStatus;
use Carbon\CarbonInterface;

/**
 * One delivery, bounce or complaint receipt posted by the mail server.
 *
 * `providerStatus` keeps the raw token the provider used, because the report is
 * often the only place an operator can see why a mailbox refused the message.
 */
final class EmailDeliveryReport
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $reference,
        public readonly EmailMessageStatus $status,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $providerEventId = null,
        public readonly ?string $providerStatus = null,
        public readonly ?string $error = null,
        public readonly ?CarbonInterface $occurredAt = null,
        public readonly array $payload = [],
    ) {}
}
