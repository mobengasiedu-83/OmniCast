<?php

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();

    [$this->client, $this->wallet, $this->user] = clientWithWallet(balance: 10.0);
});

it('lists only the purchases raised for the signed-in client', function (): void {
    $invoice = Invoice::factory()->for($this->client)->create(['number' => 'INV-OWN-001']);

    Invoice::factory()->create(['number' => 'INV-OTHER-001']);

    $this->actingAs($this->user)
        ->get(route('invoices.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('invoices/index')
            ->where('client.id', $this->client->id)
            ->has('invoices.data', 1)
            ->where('invoices.data.0.number', 'INV-OWN-001')
            ->where('invoices.data.0.download_url', route('invoices.download', $invoice))
            ->where('summary.count', 1)
            ->where('summary.paid', 1)
            ->where('summary.spent', 243.75)
            ->where('can.download', true));
});

it('searches the purchases by invoice number', function (): void {
    Invoice::factory()->for($this->client)->create(['number' => 'INV-ALPHA-01']);
    Invoice::factory()->for($this->client)->create(['number' => 'INV-BETA-01']);

    $this->actingAs($this->user)
        ->get(route('invoices.index', ['search' => 'ALPHA']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('invoices.data', 1)
            ->where('invoices.data.0.number', 'INV-ALPHA-01')
            ->where('filters.search', 'ALPHA'));
});

it('filters the purchases by status', function (): void {
    Invoice::factory()->for($this->client)->create();
    Invoice::factory()->for($this->client)->voided()->create();

    $this->actingAs($this->user)
        ->get(route('invoices.index', ['status' => InvoiceStatus::Void->value]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('invoices.data', 1)
            ->where('invoices.data.0.status', InvoiceStatus::Void->value)
            ->where('summary.count', 2)
            ->where('summary.paid', 1));
});

it('shows a platform user without a client in view an empty page', function (): void {
    Invoice::factory()->for($this->client)->create();

    $this->actingAs(platformUser())
        ->get(route('invoices.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('invoices/index')
            ->where('client', null)
            ->has('invoices.data', 0)
            ->where('summary.count', 0));
});
