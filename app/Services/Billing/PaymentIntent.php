<?php

namespace App\Services\Billing;

/**
 * What the client is paying for, as the gateway needs to hear it.
 */
class PaymentIntent
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<string>  $channels
     */
    public function __construct(
        public readonly string $reference,
        public readonly string $email,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $callbackUrl,
        public readonly string $description,
        public readonly array $metadata = [],
        public readonly array $channels = [],
    ) {}

    /**
     * The amount in the subunit the gateway quotes (pesewas, kobo, cents).
     */
    public function subunits(): int
    {
        return (int) round($this->amount * 100);
    }
}
