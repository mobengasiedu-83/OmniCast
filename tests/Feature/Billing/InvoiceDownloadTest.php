<?php

use App\Models\Invoice;

beforeEach(function (): void {
    [$this->client, $this->wallet, $this->user] = clientWithWallet(balance: 10.0);
});

it('streams the client invoice as a downloadable PDF', function (): void {
    $invoice = Invoice::factory()->for($this->client)->create(['number' => 'INV-2026-0001']);

    $response = $this->actingAs($this->user)
        ->get(route('invoices.download', $invoice));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertDownload('invoice-inv-2026-0001.pdf');

    $document = (string) $response->getContent();

    expect($document)->toStartWith('%PDF-1.4')
        ->and($document)->toContain('INV-2026-0001');
});

it('refuses the invoice of another client', function (): void {
    [$otherClient] = clientWithWallet();

    $invoice = Invoice::factory()->for($otherClient)->create();

    $this->actingAs($this->user)
        ->get(route('invoices.download', $invoice))
        ->assertForbidden();
});

it('sends a guest to the login screen instead of the PDF', function (): void {
    $invoice = Invoice::factory()->for($this->client)->create();

    $this->get(route('invoices.download', $invoice))
        ->assertRedirect(route('login'));
});
