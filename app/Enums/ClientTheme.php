<?php

namespace App\Enums;

/**
 * The colour themes a client tenant can skin its workspace with.
 *
 * Neutrals stay in the OmniCast palette: a theme only swaps the brand hue
 * (buttons, links, sidebar highlights, charts) so every tenant can recognise
 * its own workspace at a glance while the platform keeps one structure.
 *
 * Each case is mirrored by a `[data-theme='…']` block in `resources/css/app.css`
 * and applied to the `<html>` element by `resources/views/app.blade.php`.
 */
enum ClientTheme: string
{
    case Ember = 'ember';
    case Lagoon = 'lagoon';
    case Forest = 'forest';
    case Grape = 'grape';
    case Sunset = 'sunset';
    case Midnight = 'midnight';

    public function label(): string
    {
        return match ($this) {
            self::Ember => 'Ember',
            self::Lagoon => 'Lagoon',
            self::Forest => 'Forest',
            self::Grape => 'Grape',
            self::Sunset => 'Sunset',
            self::Midnight => 'Midnight',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Ember => 'The OmniCast default: warm ember orange on soft ivory.',
            self::Lagoon => 'Cool teal for calm, clinical workspaces.',
            self::Forest => 'Deep green for operations that live outdoors.',
            self::Grape => 'Violet with a little more personality.',
            self::Sunset => 'Rose coral for bold, consumer-facing brands.',
            self::Midnight => 'Indigo for regulated, late-night teams.',
        };
    }

    /**
     * The theme the platform falls back to, and the one platform staff see.
     */
    public static function default(): self
    {
        return self::Ember;
    }

    /**
     * Resolve a stored value, falling back to the platform default.
     */
    public static function resolve(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::default();
    }

    /**
     * The catalogue the theme picker renders.
     *
     * @return list<array{value: string, label: string, description: string, is_default: bool}>
     */
    public static function options(): array
    {
        return array_map(fn (self $theme): array => [
            'value' => $theme->value,
            'label' => $theme->label(),
            'description' => $theme->description(),
            'is_default' => $theme === self::default(),
        ], self::cases());
    }
}
