<?php

namespace App\Enums;

/**
 * Who a category is offered to.
 *
 * The public contact form offers `Contact`, the client ticket form offers
 * `Ticket`, and a category marked `Both` appears in each. Keeping the choice on
 * the category means an operator can add "Sales" to the public form and to the
 * client portal without touching either screen.
 */
enum SupportCategoryAudience: string
{
    case Contact = 'contact';
    case Ticket = 'ticket';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Contact => 'Public contact form',
            self::Ticket => 'Client ticket form',
            self::Both => 'Public form and client tickets',
        };
    }

    /**
     * Whether a category with this audience is offered for the given form.
     */
    public function covers(SupportTicketType $type): bool
    {
        return match ($this) {
            self::Both => true,
            self::Contact => $type === SupportTicketType::Enquiry,
            self::Ticket => $type === SupportTicketType::Ticket,
        };
    }

    /**
     * The audience and its label, for a select control.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function catalogue(): array
    {
        return array_map(fn (self $audience): array => [
            'value' => $audience->value,
            'label' => $audience->label(),
        ], self::cases());
    }

    /**
     * The audience values, as they are stored.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $audience): string => $audience->value, self::cases());
    }
}
