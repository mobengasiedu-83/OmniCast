<?php

use App\Enums\LegalDocumentType;
use App\Models\LegalDocumentVersion;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

it('lists the published documents to a visitor', function (): void {
    publishLegalDocuments();

    $this->get(route('legal.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('legal/index')
            ->has('documents', count(LegalDocumentType::cases()))
            ->where('documents', fn (Collection $documents): bool => $documents->pluck('slug')->sort()->values()->all()
                === collect(LegalDocumentType::values())->sort()->values()->all())
            ->where('documents', fn (Collection $documents): bool => $documents
                ->every(fn (array $document): bool => $document['version'] === 'v1.0')));
});

it('renders a published document as readable html', function (): void {
    legalDocument(LegalDocumentType::Terms);

    $this->get(route('legal.show', 'terms'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('legal/show')
            ->where('document.slug', 'terms')
            ->where('document.title', 'Terms and Conditions')
            ->where('document.version', 'v1.0')
            ->where('others', [])
            ->etc());
});

it('renders the markdown body as html', function (): void {
    legalDocument(LegalDocumentType::AcceptableUse);

    $this->get(route('legal.show', 'acceptable-use'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('document.html', fn (string $html): bool => str_contains($html, '<h2>1. A clause</h2>')
                && str_contains($html, '<li>One bullet</li>')
                && str_contains($html, 'This policy is binding.')));
});

it('hides a document that has nothing published', function (): void {
    $document = legalDocument(LegalDocumentType::Terms);

    LegalDocumentVersion::query()->where('legal_document_id', $document->id)->delete();

    $this->get(route('legal.show', 'terms'))->assertNotFound();
    $this->get(route('legal.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('documents', 0));
});

it('hides a document the platform has withdrawn', function (): void {
    legalDocument(LegalDocumentType::Terms, '1.0', ['is_active' => false]);

    $this->get(route('legal.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('documents', 0));

    $this->get(route('legal.show', 'terms'))->assertNotFound();
});

it('serves every published policy without signing in', function (): void {
    publishLegalDocuments();

    $this->assertGuest();

    foreach (LegalDocumentType::values() as $slug) {
        $this->get("/legal/{$slug}")->assertOk();
    }
});

it('serves a print-ready copy of a published policy', function (): void {
    legalDocument(LegalDocumentType::Terms);

    $this->get(route('legal.print', 'terms'))
        ->assertOk()
        ->assertSee('Terms and Conditions')
        ->assertSee('v1.0')
        ->assertSee('This policy is binding.')
        ->assertSee('<li>One bullet</li>', false);
});

it('does not print a policy that has nothing published', function (): void {
    legalDocument(LegalDocumentType::Terms);

    $this->get(route('legal.print', 'cookie-policy'))->assertNotFound();
});
