<?php

namespace App\Services\Billing;

/**
 * What the gateway's charge API made of a direct mobile money request.
 *
 * A charge starts in one of three places: the wallet needs a one time password
 * (`send_otp`), the payer has to approve a prompt on the handset
 * (`pay_offline`), or the wallet answered straight away. Anything else is a
 * refusal or a transaction still in flight, so the payment is only ever
 * settled from `verify()`.
 */
final class MobileMoneyCharge
{
    /**
     * The statuses that mean the wallet has not answered yet.
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
        public readonly string $status,
        public readonly string $reference,
        public readonly string $message,
        public readonly array $raw = [],
    ) {}

    /**
     * Read the `data` object of a `/charge` or `/charge/submit_otp` response.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromGateway(string $reference, array $data): self
    {
        $status = is_string($data['status'] ?? null) ? $data['status'] : 'pending';
        $message = $data['display_text'] ?? $data['gateway_response'] ?? null;

        return new self(
            status: $status,
            reference: is_string($data['reference'] ?? null) ? $data['reference'] : $reference,
            message: is_string($message) && $message !== '' ? $message : self::defaultMessage($status),
            raw: $data,
        );
    }

    /**
     * Whether the wallet sent the payer a code we have to submit.
     */
    public function requiresOtp(): bool
    {
        return $this->status === 'send_otp';
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function isPending(): bool
    {
        return in_array($this->status, self::IN_FLIGHT, true);
    }

    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'reversed', 'abandoned'], true);
    }

    /**
     * What the client is waiting for, in their words.
     */
    public function label(): string
    {
        return match (true) {
            $this->requiresOtp() => 'Waiting for the one time password',
            $this->isSuccessful() => 'Paid',
            $this->isFailed() => 'Not completed',
            $this->status === 'pay_offline' => 'Waiting for approval on the phone',
            default => 'Waiting for the wallet',
        };
    }

    /**
     * A sentence to show when the gateway sent none of its own.
     */
    private static function defaultMessage(string $status): string
    {
        return match ($status) {
            'send_otp' => 'Approve the payment with the one time password the wallet sent to the phone.',
            'pay_offline' => 'Approve the payment prompt on the phone to finish.',
            'success' => 'The wallet approved the payment.',
            'failed', 'reversed', 'abandoned' => 'The wallet did not approve the payment.',
            default => 'The wallet has not answered yet. Approve the prompt on the phone and check again.',
        };
    }
}
