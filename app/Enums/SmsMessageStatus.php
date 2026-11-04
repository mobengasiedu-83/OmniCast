<?php

namespace App\Enums;

/**
 * Where a single outbound message stands in its lifecycle.
 *
 * `queued` is written before the provider is contacted, `submitted` once the
 * provider accepted the message, and the remaining states are driven by the
 * delivery receipts the network posts back. Everything from `delivered`
 * onwards never changes again.
 */
enum SmsMessageStatus: string
{
    case Queued = 'queued';
    case Submitted = 'submitted';
    case Delivered = 'delivered';
    case Undelivered = 'undelivered';
    case Expired = 'expired';
    case Rejected = 'rejected';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Submitted => 'Submitted',
            self::Delivered => 'Delivered',
            self::Undelivered => 'Undelivered',
            self::Expired => 'Expired',
            self::Rejected => 'Rejected',
            self::Failed => 'Failed',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Delivered,
            self::Undelivered,
            self::Expired,
            self::Rejected,
            self::Failed,
        ], true);
    }

    /**
     * Whether the message reached the recipient handset.
     */
    public function wasDelivered(): bool
    {
        return $this === self::Delivered;
    }

    /**
     * Whether the campaign may send the message to the recipient again.
     */
    public function isRetryable(): bool
    {
        return in_array($this, [self::Undelivered, self::Expired, self::Rejected, self::Failed], true);
    }

    /**
     * Map a delivery receipt token onto a status.
     *
     * Receipts arrive from SMPP providers, Kannel and HTTP aggregators, so the
     * token is compared case and separator insensitively: `DELIVRD`, `delivrd`
     * and `Delivered` all mean the handset confirmed the message.
     */
    public static function fromProvider(string $token): ?self
    {
        $normalised = preg_replace('/[^A-Z0-9]+/', '', mb_strtoupper(trim($token))) ?? '';

        return match ($normalised) {
            'QUEUED', 'PENDING', 'BUFFERED' => self::Queued,
            'SUBMITTED', 'ACCEPTD', 'ACCEPTED', 'ENROUTE', 'SENT' => self::Submitted,
            'DELIVRD', 'DELIVERED', 'DELIVERYSUCCESS' => self::Delivered,
            'UNDELIV', 'UNDELIVERED', 'UNDELIVERABLE' => self::Undelivered,
            'EXPIRED' => self::Expired,
            'REJECTD', 'REJECTED' => self::Rejected,
            'FAILED', 'FAILURE', 'ERROR', 'DELIVERYFAILED' => self::Failed,
            // `UNKNOWN` means the carrier has no verdict yet: leave the message
            // where it is and let the provider send a later receipt.
            default => null,
        };
    }

    /**
     * The event type a receipt for this status appends to the message log.
     */
    public function eventType(): SmsMessageEventType
    {
        return match ($this) {
            self::Queued, self::Submitted => SmsMessageEventType::Submitted,
            self::Delivered => SmsMessageEventType::Delivered,
            self::Undelivered => SmsMessageEventType::Undelivered,
            self::Expired => SmsMessageEventType::Expired,
            self::Rejected => SmsMessageEventType::Rejected,
            self::Failed => SmsMessageEventType::Failed,
        };
    }
}
