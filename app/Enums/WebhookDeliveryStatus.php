<?php

namespace App\Enums;

/**
 * Where a webhook delivery stands.
 *
 * A delivery only reaches a terminal state once the subscriber answered with a
 * 2xx, the retry window ran out, or the signature could not be produced.
 */
enum WebhookDeliveryStatus: string
{
    case Pending = 'pending';
    case Delivering = 'delivering';
    case Delivered = 'delivered';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Delivering => 'Delivering',
            self::Delivered => 'Delivered',
            self::Failed => 'Failed',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Failed], true);
    }

    /**
     * The tone the status badge renders in, as a `Badge` variant.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Delivered => 'success',
            self::Failed => 'destructive',
            self::Delivering => 'warning',
            self::Pending => 'muted',
        };
    }
}
