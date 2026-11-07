/**
 * A colour theme a client tenant can skin its workspace with.
 *
 * Mirrors `App\Enums\ClientTheme::options()`; the palette itself lives in
 * `resources/css/app.css` behind `[data-theme='<value>']`.
 */
export type ThemeOption = {
    value: string;
    label: string;
    description: string;
    is_default: boolean;
};
