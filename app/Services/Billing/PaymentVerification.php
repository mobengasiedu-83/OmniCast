<?php

namespace App\Services\Billing;

use Carbon\CarbonInterface;

/**
 * What the gateway says about a payment it was asked to verify.
 */
class PaymentVerification
{
    /**
     * The gateway statuses that mean a charge is still on its way.
     *
     * @var list<string>
     */
    private const IN_FLIGHT = [
        'pending', 'ongoing', 'processing', 'queued',
        'pay_offline', 'send_otp', 'send_phone', 'send_birthday', 'send_address', 'attention',
    ];

    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly bool $successful,
        public readonly float $amount,
        public readonly string $currency,
        public readonly ?string $providerReference = null,
        public readonly ?string $channel = null,
        public readonly ?CarbonInterface $paidAt = null,
        public readonly ?string $failureReason = null,
        public readonly array $raw = [],
        public readonly ?string $status = null,
    ) {}

    /**
     * Whether the gateway collected at least the amount we asked for.
     */
    public function covers(float $amount): bool
    {
        return round($this->amount, 2) + 0.001 >= round($amount, 2);
    }

    /**
     * Whether the wallet still has to answer, rather than having refused.
     */
    public function isPending(): bool
    {
        return in_array($this->status, self::IN_FLIGHT, true);
    }

    /**
     * A verification for a payment that never reached the gateway.
     */
    public static function unsuccessful(string $reason): self
    {
        return new self(
            successful: false,
            amount: 0.0,
            currency: (string) config('billing.currency', 'GHS'),
            failureReason: $reason,
        );
    }
}
