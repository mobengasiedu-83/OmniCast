<?php

namespace App\Enums;

/**
 * Where a single outbound email stands in its lifecycle.
 *
 * `queued` is written before the transport is contacted and `submitted` once the
 * mail server accepted the message. SMTP gives no delivery receipt of its own,
 * so the remaining states are driven by the bounce and complaint feedback the
 * provider posts to the signed email webhook. Everything from `delivered`
 * onwards never changes again.
 */
enum EmailMessageStatus: string
{
    case Queued = 'queued';
    case Submitted = 'submitted';
    case Delivered = 'delivered';
    case Bounced = 'bounced';
    case Complained = 'complained';
    case Rejected = 'rejected';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Submitted => 'Submitted',
            self::Delivered => 'Delivered',
            self::Bounced => 'Bounced',
            self::Complained => 'Complained',
            self::Rejected => 'Rejected',
            self::Failed => 'Failed',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Delivered,
            self::Bounced,
            self::Complained,
            self::Rejected,
            self::Failed,
        ], true);
    }

    /**
     * Whether the message reached the recipient mailbox.
     */
    public function wasDelivered(): bool
    {
        return $this === self::Delivered;
    }

    /**
     * Whether the campaign may send the message to the recipient again.
     *
     * A complaint is never retried: the recipient asked not to be mailed, so the
     * address is suppressed instead of being sent the same message twice.
     */
    public function isRetryable(): bool
    {
        return in_array($this, [self::Bounced, self::Rejected, self::Failed], true);
    }

    /**
     * Whether the receipt should also suppress the recipient address.
     */
    public function suppressesRecipient(): bool
    {
        return in_array($this, [self::Bounced, self::Complained], true);
    }

    /**
     * Map a feedback token onto a status.
     *
     * Tokens arrive from SMTP bounce reports (DSN), provider webhooks (SES,
     * SendGrid, Mailgun, Postmark) and HTTP aggregators, so they are compared
     * case and separator insensitively: `delivered`, `250`, `DELIVERED` and
     * `Delivery-Success` all mean the mailbox confirmed the message.
     */
    public static function fromProvider(string $token): ?self
    {
        $normalised = preg_replace('/[^A-Z0-9]+/', '', mb_strtoupper(trim($token))) ?? '';

        return match ($normalised) {
            'QUEUED', 'PENDING', 'DEFERRED', 'BUFFERED' => self::Queued,
            'SUBMITTED', 'ACCEPTED', 'ACCEPTD', 'SENT', '250', '2XX' => self::Submitted,
            'DELIVERED', 'DELIVRD', 'DELIVEREDSUCCESS', 'DELIVEREDOK' => self::Delivered,
            'BOUNCE', 'BOUNCED', 'HARDBOUNCE', 'SOFTBOUNCE', 'UNDELIVERED', 'UNDELIV', '550', '5XX' => self::Bounced,
            'COMPLAINT', 'COMPLAINED', 'SPAM', 'SPAMCOMPLAINT', 'ABUSE' => self::Complained,
            'REJECTED', 'REJECTD', 'BLOCKED', 'SUPPRESSED' => self::Rejected,
            'FAILED', 'FAILURE', 'ERROR', 'DELIVERYFAILED' => self::Failed,
            // An unknown verdict leaves the message where it is; the provider
            // may still post a later receipt for it.
            default => null,
        };
    }

    /**
     * The event type a receipt for this status appends to the message log.
     */
    public function eventType(): EmailMessageEventType
    {
        return match ($this) {
            self::Queued, self::Submitted => EmailMessageEventType::Submitted,
            self::Delivered => EmailMessageEventType::Delivered,
            self::Bounced => EmailMessageEventType::Bounced,
            self::Complained => EmailMessageEventType::Complained,
            self::Rejected => EmailMessageEventType::Rejected,
            self::Failed => EmailMessageEventType::Failed,
        };
    }
}
