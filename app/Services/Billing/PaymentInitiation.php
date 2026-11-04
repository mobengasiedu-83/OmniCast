<?php

namespace App\Services\Billing;

/**
 * The checkout the gateway created for a payment.
 */
class PaymentInitiation
{
    public function __construct(
        public readonly string $authorizationUrl,
        public readonly ?string $accessCode = null,
        public readonly ?string $providerReference = null,
    ) {}
}
