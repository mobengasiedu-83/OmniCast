<?php

use App\Enums\LegalAcceptanceContext;
use App\Enums\LegalDocumentType;
use App\Enums\OrganisationDpaStatus;
use App\Services\Legal\LegalLibrary;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    publishLegalDocuments();
});

it('lists acceptances with the organisation that gave them', function (): void {
    [$client, , $user] = clientWithWallet();

    app(LegalLibrary::class)->acceptOutstanding($user, LegalAcceptanceContext::OrganisationSettings);

    $this->actingAs(platformUser())
        ->get(route('admin.legal-acceptances.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/legal/acceptances')
            ->has('acceptances.data', count(LegalDocumentType::cases()))
            ->where('acceptances.data.0.client', $client->name)
            ->where('acceptances.data.0.email', $user->email)
            ->where('stats.total', count(LegalDocumentType::cases()))
            ->has('documents', count(LegalDocumentType::cases()))
            ->has('contexts', count(LegalAcceptanceContext::cases())));
});

it('narrows the register by document and by screen', function (): void {
    [, , $user] = clientWithWallet();
    app(LegalLibrary::class)->acceptOutstanding($user, LegalAcceptanceContext::OrganisationSettings);

    $this->actingAs(platformUser())
        ->get(route('admin.legal-acceptances.index', ['document' => 'terms']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('acceptances.data', 1));

    $this->actingAs(platformUser())
        ->get(route('admin.legal-acceptances.index', ['context' => LegalAcceptanceContext::Dpa->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('acceptances.data', 0));
});

it('downloads the register as csv', function (): void {
    [, , $user] = clientWithWallet();
    app(LegalLibrary::class)->acceptOutstanding($user, LegalAcceptanceContext::OrganisationSettings);

    $response = $this->actingAs(platformUser())
        ->get(route('admin.legal-acceptances.export'));

    $response->assertOk();

    $body = $response->streamedContent();

    expect($body)
        ->toContain('Document,Version,Organisation')
        ->toContain('Terms and Conditions')
        ->toContain('Organisation settings')
        ->toContain($user->email);
});

it('refuses the register to a client account', function (): void {
    [, , $user] = clientWithWallet();

    $this->actingAs($user)
        ->get(route('admin.legal-acceptances.index'))
        ->assertForbidden();
});

it('lists organisations without an agreement', function (): void {
    [$client] = clientWithWallet();

    $this->actingAs(platformUser())
        ->get(route('admin.legal-dpas.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/legal/dpas')
            ->has('clients.data', 1)
            ->where('clients.data.0.client', $client->name)
            ->where('clients.data.0.status', OrganisationDpaStatus::NotStarted->value)
            ->where('stats.without', 1));
});

it('records a countersigned agreement', function (): void {
    [$client] = clientWithWallet();

    $this->actingAs(platformUser())
        ->put(route('admin.legal-dpas.update', $client), [
            'status' => OrganisationDpaStatus::Signed->value,
            'signatory_name' => 'Ama Mensah',
            'signatory_email' => 'ama@example.test',
            'signed_at' => now()->toDateString(),
            'notes' => 'Countersigned copy received by email.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $dpa = $client->fresh()->organisationDpa;

    expect($dpa)->not->toBeNull()
        ->and($dpa->status)->toBe(OrganisationDpaStatus::Signed)
        ->and($dpa->signatory_name)->toBe('Ama Mensah')
        ->and($dpa->signed_at)->not->toBeNull();
});

it('refuses to record an agreement without a status', function (): void {
    [$client] = clientWithWallet();

    $this->actingAs(platformUser())
        ->put(route('admin.legal-dpas.update', $client), ['status' => 'not-a-status'])
        ->assertSessionHasErrors('status');
});
