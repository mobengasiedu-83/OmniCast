<?php

namespace App\Services\Email;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Washes the markup a campaign or template author wrote before it is stored.
 *
 * The body is delivered verbatim inside the broadcast shell, so it is authored
 * with a rich-text editor. That editor can paste markup from Word and Outlook,
 * which arrives full of `mso-*` styles and Office-only tags, and a hostile paste
 * could carry a script or an event handler. Everything is stripped down to the
 * elements and CSS declarations an email actually renders, so what the reviewer
 * approved is what the recipient receives.
 */
class EmailBodySanitizer
{
    /**
     * The CSS declarations a marketing email is allowed to keep.
     *
     * Anything else (positioning, behaviour, imports) is dropped: no mail client
     * needs it, and it is where style-based attacks live.
     */
    private const ALLOWED_PROPERTIES = [
        'color', 'background', 'background-color', 'font', 'font-family', 'font-size',
        'font-style', 'font-weight', 'line-height', 'letter-spacing', 'text-align',
        'text-decoration', 'text-transform', 'vertical-align', 'white-space',
        'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
        'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
        'border', 'border-top', 'border-right', 'border-bottom', 'border-left',
        'border-collapse', 'border-color', 'border-radius', 'border-style', 'border-width',
        'width', 'min-width', 'max-width', 'height', 'min-height', 'max-height',
        'display',
    ];

    /**
     * The elements the editor may keep, in addition to the framework's safe set.
     *
     * @var list<string>
     */
    private const STYLED_ELEMENTS = [
        'p', 'div', 'span', 'a', 'img', 'h1', 'h2', 'h3', 'h4', 'ul', 'ol', 'li',
        'blockquote', 'hr', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th',
        'strong', 'b', 'em', 'i', 'u', 's', 'br', 'pre', 'code', 'small', 'figure',
        'figcaption', 'center', 'mark',
    ];

    /**
     * Washed markup, ready to store and deliver.
     */
    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        return $this->scrubStyles($this->sanitizer()->sanitize($html));
    }

    /**
     * The configured sanitiser.
     */
    protected function sanitizer(): HtmlSanitizer
    {
        return new HtmlSanitizer($this->configuration());
    }

    /**
     * The allow-list the body is cleaned against.
     *
     * Links may only point at a web page or an address, media may only be a web
     * URL or a pasted image, and scripts, frames, forms and stylesheets are
     * blocked outright.
     */
    protected function configuration(): HtmlSanitizerConfig
    {
        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks()
            ->allowMediaSchemes(['http', 'https', 'data'])
            ->allowRelativeMedias()
            ->withMaxInputLength(200000);

        foreach (self::STYLED_ELEMENTS as $element) {
            $config = $config->allowElement($element, ['style', 'align', 'width', 'height', 'colspan', 'rowspan']);
        }

        return $config
            ->allowAttribute('style', self::STYLED_ELEMENTS)
            ->allowAttribute('role', ['table', 'td', 'tr'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer');
    }

    /**
     * Keep only the CSS declarations an email can safely render.
     */
    protected function scrubStyles(string $html): string
    {
        return preg_replace_callback(
            '/\sstyle\s*=\s*"([^"]*)"/i',
            fn (array $matches): string => ' style="'.$this->scrubDeclarations($matches[1]).'"',
            $html,
        ) ?? $html;
    }

    /**
     * Drop every declaration that is not on the allow-list, or whose value tries
     * to reach outside the message.
     */
    protected function scrubDeclarations(string $style): string
    {
        $kept = [];

        foreach (explode(';', $style) as $declaration) {
            $parts = explode(':', $declaration, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $property = strtolower(trim($parts[0]));
            $value = trim($parts[1]);

            if ($value === '' || ! in_array($property, self::ALLOWED_PROPERTIES, true)) {
                continue;
            }

            if (preg_match('/url\s*\(|expression|javascript:|vbscript:|@import|behavior/i', $value) === 1) {
                continue;
            }

            $kept[] = $property.': '.$value;
        }

        return implode('; ', $kept);
    }
}
