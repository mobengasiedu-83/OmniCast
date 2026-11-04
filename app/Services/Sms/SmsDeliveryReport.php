<?php

namespace App\Services\Sms;

use App\Enums\SmsMessageStatus;
use Carbon\CarbonInterface;

/**
 * One delivery receipt posted by the network.
 *
 * `providerStatus` keeps the raw token the provider used, because the report is
 * often the only place an operator can see why a carrier refused a message.
 */
final class SmsDeliveryReport
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $reference,
        public readonly SmsMessageStatus $status,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $providerEventId = null,
        public readonly ?string $providerStatus = null,
        public readonly ?string $error = null,
        public readonly ?CarbonInterface $occurredAt = null,
        public readonly array $payload = [],
    ) {}
}
