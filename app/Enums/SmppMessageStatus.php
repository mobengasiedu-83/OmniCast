<?php

namespace App\Enums;

/**
 * The lifecycle of a message submitted through the SMPP server.
 *
 * `stat()` is the SMPP `message_state` token sent back to the ESME in the
 * `deliver_sm` receipt.
 */
enum SmppMessageStatus: string
{
    case Accepted = 'accepted';
    case Submitted = 'submitted';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'Accepted',
            self::Submitted => 'Submitted to carrier',
            self::Delivered => 'Delivered',
            self::Failed => 'Failed',
            self::Rejected => 'Rejected',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Failed, self::Rejected], true);
    }

    /**
     * The `stat` token an SMPP delivery receipt carries.
     */
    public function stat(): string
    {
        return match ($this) {
            self::Accepted => 'ACCEPTD',
            self::Submitted => 'ENROUTE',
            self::Delivered => 'DELIVRD',
            self::Failed => 'UNDELIV',
            self::Rejected => 'REJECTD',
        };
    }

    /**
     * The `message_state` integer an SMPP delivery receipt carries.
     *
     * The values come from the SMPP 3.4 `message_state` table, where 2 is the
     * only one an ESME should ever treat as a confirmed handset delivery.
     */
    public function messageState(): int
    {
        return match ($this) {
            self::Accepted => 6,
            self::Submitted => 1,
            self::Delivered => 2,
            self::Failed => 5,
            self::Rejected => 8,
        };
    }

    /**
     * Map a provider receipt token onto a message state.
     */
    public static function fromStat(string $stat): ?self
    {
        return match (strtoupper(trim($stat))) {
            'ACCEPTD', 'ACCEPTED' => self::Accepted,
            'ENROUTE', 'SUBMITTED' => self::Submitted,
            'DELIVRD', 'DELIVERED' => self::Delivered,
            'UNDELIV', 'EXPIRED', 'DELETED', 'UNKNOWN' => self::Failed,
            'REJECTD', 'REJECTED' => self::Rejected,
            default => null,
        };
    }
}
