<?php

namespace App\Enums;

/**
 * What a row in the support register is.
 *
 * A public contact form writes an enquiry: the team answers it and that is the
 * end of it. A signed-in client writes a ticket: it has an owner, a status and a
 * thread, and the client follows it from their own screen.
 */
enum SupportTicketType: string
{
    case Enquiry = 'enquiry';
    case Ticket = 'ticket';

    public function label(): string
    {
        return match ($this) {
            self::Enquiry => 'Enquiry',
            self::Ticket => 'Support ticket',
        };
    }

    public function pluralLabel(): string
    {
        return match ($this) {
            self::Enquiry => 'Enquiries',
            self::Ticket => 'Support tickets',
        };
    }

    /**
     * The prefix of a reference, so the number itself says what it is.
     */
    public function prefix(): string
    {
        return match ($this) {
            self::Enquiry => 'ENQ',
            self::Ticket => 'TKT',
        };
    }

    /**
     * The type values, as they are stored.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type): string => $type->value, self::cases());
    }
}
