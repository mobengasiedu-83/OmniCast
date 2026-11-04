<?php

namespace App\Enums;

/**
 * How the body of an email broadcast is authored.
 *
 * An HTML broadcast is always sent with a generated plain-text alternative, so
 * recipients whose client refuses HTML still read the message.
 */
enum EmailFormat: string
{
    case Html = 'html';
    case Text = 'text';

    public function label(): string
    {
        return match ($this) {
            self::Html => 'HTML',
            self::Text => 'Plain text',
        };
    }

    /**
     * Whether the body carries markup.
     */
    public function isHtml(): bool
    {
        return $this === self::Html;
    }

    /**
     * The select options used by the campaign wizard.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $format): array => ['value' => $format->value, 'label' => $format->label()],
            self::cases(),
        );
    }
}
