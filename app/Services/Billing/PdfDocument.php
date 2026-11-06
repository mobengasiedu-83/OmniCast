<?php

namespace App\Services\Billing;

use DateTimeImmutable;
use Illuminate\Support\Str;

/**
 * A small PDF 1.4 writer: text, rules and filled boxes on A4 pages.
 *
 * The platform ships no PDF package and no headless browser, so the invoice
 * documents are assembled here from the two Helvetica fonts every reader
 * already has built in. Coordinates are given from the top left corner, the
 * way a page reads on screen, and flipped into PDF's bottom-up space as each
 * operator is written.
 */
class PdfDocument
{
    /**
     * The A4 page size in PostScript points (1/72 inch).
     */
    public const WIDTH = 595.28;

    public const HEIGHT = 841.89;

    /**
     * The palette the document is painted with.
     */
    public const INK = '#111827';

    public const MUTED = '#6b7280';

    public const RULE = '#e5e7eb';

    public const BRAND = '#f47d11';

    public const SURFACE = '#f9fafb';

    /**
     * The glyph widths of Helvetica, in thousandths of the font size, for the
     * printable ASCII range that starts at the space character.
     *
     * Bold runs are measured with the same table: the difference is under a
     * point per character and only ever shifts right-aligned money by a hair.
     *
     * @var list<int>
     */
    protected const GLYPH_WIDTHS = [
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
        1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
        333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
        556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
    ];

    /**
     * The content stream of the page being drawn.
     */
    protected string $content = '';

    /**
     * The content streams of the pages already finished.
     *
     * @var list<string>
     */
    protected array $pages = [];

    public function __construct(
        protected string $title = 'Document',
        protected ?DateTimeImmutable $createdAt = null,
    ) {
        $this->createdAt ??= new DateTimeImmutable;
    }

    /**
     * Finish the current page and start a blank one beneath it.
     */
    public function newPage(): void
    {
        $this->pages[] = $this->content;
        $this->content = '';
    }

    /**
     * Draw one line of text with its baseline `$y` points below the top edge.
     */
    public function text(
        string $text,
        float $x,
        float $y,
        float $size = 10.0,
        bool $bold = false,
        string $color = self::INK,
    ): void {
        if ($text === '') {
            return;
        }

        $this->content .= sprintf(
            '%s rg BT /%s %s Tf 1 0 0 1 %s %s Tm (%s) Tj ET'."\n",
            $this->color($color),
            $bold ? 'F2' : 'F1',
            $this->number($size),
            $this->number($x),
            $this->number(self::HEIGHT - $y),
            $this->escape($text),
        );
    }

    /**
     * Draw a line of text so that its right edge sits on `$right`.
     */
    public function textRight(
        string $text,
        float $right,
        float $y,
        float $size = 10.0,
        bool $bold = false,
        string $color = self::INK,
    ): void {
        $this->text($text, $right - $this->textWidth($text, $size, $bold), $y, $size, $bold, $color);
    }

    /**
     * Draw a horizontal rule between two points.
     */
    public function line(
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        float $weight = 0.7,
        string $color = self::RULE,
    ): void {
        $this->content .= sprintf(
            'q %s RG %s w %s %s m %s %s l S Q'."\n",
            $this->color($color),
            $this->number($weight),
            $this->number($x1),
            $this->number(self::HEIGHT - $y1),
            $this->number($x2),
            $this->number(self::HEIGHT - $y2),
        );
    }

    /**
     * Fill a box whose top left corner is `$x`, `$y`.
     */
    public function fill(
        float $x,
        float $y,
        float $width,
        float $height,
        string $color = self::SURFACE,
    ): void {
        $this->content .= sprintf(
            'q %s rg %s %s %s %s re f Q'."\n",
            $this->color($color),
            $this->number($x),
            $this->number(self::HEIGHT - $y - $height),
            $this->number($width),
            $this->number($height),
        );
    }

    /**
     * The width in points of `$text` set at `$size`.
     */
    public function textWidth(string $text, float $size = 10.0, bool $bold = false): float
    {
        $width = 0;

        foreach (str_split($this->printable($text)) as $character) {
            $width += self::GLYPH_WIDTHS[ord($character) - 32] ?? 556;
        }

        return round($width / 1000 * $size * ($bold ? 1.01 : 1.0), 3);
    }

    /**
     * Cut `$text` down to what fits in `$width`, marking the cut with an ellipsis.
     */
    public function truncate(string $text, float $width, float $size = 10.0, bool $bold = false): string
    {
        if ($this->textWidth($text, $size, $bold) <= $width) {
            return $text;
        }

        $characters = str_split($text);
        $kept = '';

        while ($characters !== []) {
            $candidate = $kept.array_shift($characters);

            if ($this->textWidth($candidate.'...', $size, $bold) > $width) {
                break;
            }

            $kept = $candidate;
        }

        return rtrim($kept).'...';
    }

    /**
     * The finished document, ready to be streamed to the browser.
     */
    public function output(): string
    {
        $this->pages[] = $this->content;

        $objectCount = 5 + count($this->pages) * 2;

        /** @var array<int, string> $objects */
        $objects = array_fill(1, $objectCount, '');

        $kids = [];

        foreach ($this->pages as $index => $content) {
            $pageId = 6 + $index * 2;
            $streamId = $pageId + 1;

            $kids[] = $pageId.' 0 R';

            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '
                .$this->number(self::WIDTH).' '.$this->number(self::HEIGHT).']'
                .' /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >>'
                .' /Contents '.$streamId.' 0 R >>';

            $objects[$streamId] = '<< /Length '.strlen($content)." >>\nstream\n"
                .$content."\nendstream";
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($this->pages).' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objects[5] = '<< /Title ('.$this->escape($this->title).')'
            .' /Producer ('.$this->escape((string) config('app.name', 'OmniCast')).')'
            .' /CreationDate ('.$this->createdAt->format('YmdHis').') >>';

        $document = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";

        /** @var array<int, int> $offsets */
        $offsets = [];

        for ($id = 1; $id <= $objectCount; $id++) {
            $offsets[$id] = strlen($document);
            $document .= $id." 0 obj\n".$objects[$id]."\nendobj\n";
        }

        $startXref = strlen($document);

        $document .= 'xref'."\n".'0 '.($objectCount + 1)."\n".'0000000000 65535 f '."\n";

        for ($id = 1; $id <= $objectCount; $id++) {
            $document .= sprintf('%010d 00000 n ', $offsets[$id])."\n";
        }

        return $document.'trailer'."\n".'<< /Size '.($objectCount + 1).' /Root 1 0 R /Info 5 0 R >>'
            ."\n".'startxref'."\n".$startXref."\n".'%%EOF';
    }

    /**
     * The three colour components of a `#rrggbb` value, as PDF writes them.
     */
    protected function color(string $hex): string
    {
        $value = ltrim($hex, '#');

        if (strlen($value) !== 6) {
            $value = '111827';
        }

        return implode(' ', array_map(
            fn (string $pair): string => $this->number(round(hexdec($pair) / 255, 4)),
            str_split($value, 2),
        ));
    }

    /**
     * Format a number without an exponent and without trailing zeroes.
     */
    protected function number(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');

        return $formatted === '' || $formatted === '-0' ? '0' : $formatted;
    }

    /**
     * The printable ASCII form of `$text`, with PDF's delimiters escaped.
     */
    protected function escape(string $text): string
    {
        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\\(', '\\)'],
            $this->printable($text),
        );
    }

    /**
     * Transliterate `$text` so the built-in fonts can draw every glyph.
     */
    protected function printable(string $text): string
    {
        return preg_replace('/[^\x20-\x7E]/', ' ', Str::ascii($text)) ?? '';
    }
}
