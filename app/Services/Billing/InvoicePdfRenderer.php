<?php

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Lays an invoice out as a printable A4 document.
 *
 * The document is a letterhead, who owes it (the client), the lines that built
 * the total, the total itself and the payment that settled it. Long invoices
 * spill onto a second page with the column heads repeated.
 */
class InvoicePdfRenderer
{
    /**
     * The page margin and the right edge of the text column.
     */
    protected const MARGIN = 48.0;

    protected const RIGHT = 547.0;

    /**
     * The x position where the payment summary column starts.
     */
    protected const COLUMN = 330.0;

    /**
     * The baseline below which a line no longer fits on the page.
     */
    protected const PAGE_BOTTOM = 700.0;

    /**
     * Render the invoice into a PDF document.
     */
    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing(['client', 'payment']);

        $document = new PdfDocument(
            title: 'Invoice '.$invoice->number,
            createdAt: $invoice->issued_at?->toDateTimeImmutable(),
        );

        $this->drawHeader($document, $invoice);

        $top = $this->drawParties($document, $invoice);

        $baseline = $this->drawLines($document, $invoice, $top);

        $this->drawTotals($document, $invoice, $baseline);

        $this->drawFooter($document, $invoice);

        return $document->output();
    }

    /**
     * The platform's letterhead, the invoice number and its status.
     */
    protected function drawHeader(PdfDocument $document, Invoice $invoice): void
    {
        $issuer = $this->issuer();

        $document->text($document->truncate($issuer['name'], 250, 17, true), self::MARGIN, 60, 17, true);
        $document->text('Wallet funding and messaging services', self::MARGIN, 77, 9, false, PdfDocument::MUTED);

        $line = 94;

        $details = array_filter([
            $issuer['address'],
            $issuer['phone'],
            $issuer['email'],
            $issuer['website'],
        ]);

        foreach ($details as $detail) {
            $document->text($document->truncate($detail, 250, 9), self::MARGIN, $line, 9, false, PdfDocument::MUTED);
            $line += 13;
        }

        $this->drawStatusPill($document, $invoice);

        $document->textRight('INVOICE', self::RIGHT, 82, 22, true, PdfDocument::BRAND);
        $document->textRight($invoice->number, self::RIGHT, 102, 11, true);
        $document->textRight('Issued '.$this->moment($invoice->issued_at), self::RIGHT, 118, 9, false, PdfDocument::MUTED);

        if ($invoice->paid_at !== null) {
            $document->textRight('Paid '.$this->moment($invoice->paid_at), self::RIGHT, 131, 9, false, PdfDocument::MUTED);
        }

        $document->line(self::MARGIN, 152, self::RIGHT, 152, 1.0);
    }

    /**
     * The chip that says whether the invoice is paid, refunded or void.
     */
    protected function drawStatusPill(PdfDocument $document, Invoice $invoice): void
    {
        $label = Str::upper($invoice->status->label());
        $width = $document->textWidth($label, 8, true) + 18;

        [$background, $ink] = match ($invoice->status) {
            InvoiceStatus::Paid => ['#dcfce7', '#15803d'],
            InvoiceStatus::Refunded => ['#fef3c7', '#b45309'],
            InvoiceStatus::Void => ['#f3f4f6', '#4b5563'],
        };

        $document->fill(self::RIGHT - $width, 38, $width, 16, $background);
        $document->text($label, self::RIGHT - $width + 9, 49, 8, true, $ink);
    }

    /**
     * Who the invoice is billed to, and the payment behind it.
     *
     * @return float The baseline the line items start from.
     */
    protected function drawParties(PdfDocument $document, Invoice $invoice): float
    {
        $client = $invoice->client;

        $document->text('BILLED TO', self::MARGIN, 178, 8, true, PdfDocument::MUTED);

        $document->text(
            $document->truncate($client->name ?? 'Client account', 250, 11, true),
            self::MARGIN,
            196,
            11,
            true,
        );

        $details = array_values(array_filter([
            $client?->code !== null ? 'Client code '.$client->code : null,
            $client?->email,
            $client?->phone,
        ]));

        $line = 212;

        foreach ($details as $detail) {
            $document->text($document->truncate($detail, 250, 9), self::MARGIN, $line, 9, false, PdfDocument::MUTED);
            $line += 13;
        }

        $leftBottom = $details === [] ? 196.0 : $line - 13;

        $document->text('PAYMENT', self::COLUMN, 178, 8, true, PdfDocument::MUTED);

        $rows = array_filter([
            'Reference' => $this->paymentReference($invoice),
            'Method' => $this->channelLabel($invoice),
            'Status' => $invoice->status->label(),
        ], fn (string $value): bool => $value !== '');

        $line = 196;

        foreach ($rows as $label => $value) {
            $document->text($label, self::COLUMN, $line, 9, false, PdfDocument::MUTED);

            $document->text(
                $document->truncate($value, self::RIGHT - self::COLUMN - 80, 9, true),
                self::COLUMN + 80,
                $line,
                9,
                true,
            );

            $line += 14;
        }

        $rightBottom = $rows === [] ? 196.0 : $line - 14;

        return max($leftBottom, $rightBottom) + 34;
    }

    /**
     * The line items, restarted on a fresh page when they run long.
     *
     * @return float The baseline the totals block starts from.
     */
    protected function drawLines(PdfDocument $document, Invoice $invoice, float $top): float
    {
        $baseline = $this->drawTableHead($document, $top);

        $lines = $invoice->lines ?? [];

        if ($lines === []) {
            $lines = [[
                'description' => 'Wallet funding',
                'quantity' => 1,
                'unit_price' => (float) $invoice->subtotal,
                'amount' => (float) $invoice->subtotal,
            ]];
        }

        foreach ($lines as $line) {
            if ($baseline > self::PAGE_BOTTOM) {
                $document->newPage();
                $document->text('Invoice '.$invoice->number, self::MARGIN, 60, 10, true);
                $document->textRight('continued', self::RIGHT, 60, 9, false, PdfDocument::MUTED);
                $document->line(self::MARGIN, 70, self::RIGHT, 70, 1.0);

                $baseline = $this->drawTableHead($document, 100);
            }

            $quantity = (float) ($line['quantity'] ?? 1);
            $amount = (float) ($line['amount'] ?? 0);
            $unitPrice = (float) ($line['unit_price'] ?? $amount);

            $document->text(
                $document->truncate((string) ($line['description'] ?? 'Wallet funding'), 280, 9.5),
                56.0,
                $baseline,
                9.5,
            );

            $document->textRight($this->count($quantity), 360.0, $baseline, 9.5);
            $document->textRight($this->money($unitPrice, $invoice->currency), 452.0, $baseline, 9.5);
            $document->textRight($this->money($amount, $invoice->currency), self::RIGHT, $baseline, 9.5, true);

            $document->line(self::MARGIN, $baseline + 9, self::RIGHT, $baseline + 9, 0.5);

            $baseline += 24;
        }

        return $baseline;
    }

    /**
     * The column headings above the line items.
     */
    protected function drawTableHead(PdfDocument $document, float $top): float
    {
        $document->fill(self::MARGIN, $top - 14, self::RIGHT - self::MARGIN, 22);

        $document->text('DESCRIPTION', 56.0, $top + 1, 8, true, PdfDocument::MUTED);
        $document->textRight('QTY', 360.0, $top + 1, 8, true, PdfDocument::MUTED);
        $document->textRight('UNIT PRICE', 452.0, $top + 1, 8, true, PdfDocument::MUTED);
        $document->textRight('AMOUNT', self::RIGHT, $top + 1, 8, true, PdfDocument::MUTED);

        return $top + 24;
    }

    /**
     * The subtotal, tax and total the lines add up to.
     */
    protected function drawTotals(PdfDocument $document, Invoice $invoice, float $baseline): void
    {
        if ($baseline > self::PAGE_BOTTOM) {
            $document->newPage();
            $baseline = 100.0;
        }

        $baseline += 12;

        $rows = [['Subtotal', (float) $invoice->subtotal]];

        if ((float) $invoice->tax > 0) {
            $rows[] = ['Tax', (float) $invoice->tax];
        }

        foreach ($rows as [$label, $amount]) {
            $document->textRight($label, 452.0, $baseline, 9.5, false, PdfDocument::MUTED);
            $document->textRight($this->money($amount, $invoice->currency), self::RIGHT, $baseline, 9.5);

            $baseline += 17;
        }

        $document->line(360.0, $baseline - 4, self::RIGHT, $baseline - 4, 0.7);

        $document->textRight('Total paid', 452.0, $baseline + 16, 11.5, true);
        $document->textRight(
            $this->money((float) $invoice->total, $invoice->currency),
            self::RIGHT,
            $baseline + 16,
            11.5,
            true,
        );

        $document->text('Received with thanks.', self::MARGIN, $baseline + 16, 10, true);
        $document->text(
            $this->money((float) $invoice->total, $invoice->currency)
                .' was credited to the '.$invoice->currency.' wallet.',
            self::MARGIN,
            $baseline + 32,
            9,
            false,
            PdfDocument::MUTED,
        );
    }

    /**
     * The small print that closes the page.
     */
    protected function drawFooter(PdfDocument $document, Invoice $invoice): void
    {
        $issuer = $this->issuer();

        $document->line(self::MARGIN, 770, self::RIGHT, 770, 0.7);

        $document->text(
            $document->truncate(
                'This invoice was generated electronically and is valid without a signature.',
                self::RIGHT - self::MARGIN,
                8.5,
            ),
            self::MARGIN,
            788,
            8.5,
            false,
            PdfDocument::MUTED,
        );

        $document->text(
            $document->truncate(
                'Questions? Write to '.($issuer['email'] ?? 'our support desk').' quoting '.$invoice->number.'.',
                self::RIGHT - self::MARGIN,
                8.5,
            ),
            self::MARGIN,
            801,
            8.5,
            false,
            PdfDocument::MUTED,
        );
    }

    /**
     * The letterhead printed at the top of every invoice.
     *
     * @return array{name: string, address: string|null, phone: string|null, email: string|null, website: string|null}
     */
    protected function issuer(): array
    {
        return [
            'name' => (string) config('billing.issuer.name', config('app.name')),
            'address' => config('billing.issuer.address'),
            'phone' => config('billing.issuer.phone'),
            'email' => config('billing.issuer.email'),
            'website' => config('billing.issuer.website'),
        ];
    }

    /**
     * The gateway reference of the payment that settled the invoice.
     */
    protected function paymentReference(Invoice $invoice): string
    {
        $reference = $invoice->metadata['payment_reference'] ?? $invoice->payment?->reference;

        return is_string($reference) ? $reference : '';
    }

    /**
     * The payment channel, in the words the client saw at checkout.
     */
    protected function channelLabel(Invoice $invoice): string
    {
        $channel = $invoice->metadata['channel'] ?? $invoice->payment?->channel;

        return is_string($channel) && $channel !== '' ? Str::headline($channel) : '';
    }

    /**
     * An amount the way the client sees it on the wallet screens.
     */
    protected function money(float $amount, string $currency): string
    {
        return $currency.' '.number_format($amount, 2);
    }

    /**
     * A quantity without a trailing `.0` when it is a whole number.
     */
    protected function count(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.');
    }

    /**
     * A date as it is printed in the document header.
     */
    protected function moment(?CarbonInterface $moment): string
    {
        return $moment?->format('j M Y') ?? '-';
    }
}
