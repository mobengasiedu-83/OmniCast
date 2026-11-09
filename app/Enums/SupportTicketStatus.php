<?php

namespace App\Enums;

/**
 * Where a support conversation has got to.
 *
 * `Pending` is the honest state for "we asked the customer something"; `New`
 * means nobody has picked it up yet, which is what the reports chase.
 */
enum SupportTicketStatus: string
{
    case New = 'new';
    case Open = 'open';
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Open => 'Open',
            self::Pending => 'Waiting on customer',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    /**
     * Whether the conversation still needs someone's attention.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::New, self::Open, self::Pending], true);
    }

    /**
     * Whether the conversation has been taken out of the queue for good.
     */
    public function isSettled(): bool
    {
        return ! $this->isOpen();
    }

    /**
     * The status and its label, for a select control.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function catalogue(): array
    {
        return array_map(fn (self $status): array => [
            'value' => $status->value,
            'label' => $status->label(),
        ], self::cases());
    }

    /**
     * The status values, as they are stored.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }
}
