<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a payment gateway cannot be reached, refuses the request, or
 * answers with something we cannot read.
 */
class PaymentGatewayException extends RuntimeException
{
    /**
     * A gateway the configuration points at but that cannot take money yet.
     */
    public static function notConfigured(string $gateway, string $hint): self
    {
        return new self("The {$gateway} payment gateway is not configured. {$hint}");
    }
}
