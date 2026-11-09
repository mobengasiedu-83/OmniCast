<?php

use App\Enums\LegalAcceptanceContext;
use App\Enums\LegalDocumentType;
use App\Enums\Role;
use App\Models\LegalDocumentVersion;
use App\Services\Legal\LegalLibrary;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

it('lists every version of every policy', function (): void {
    $document = legalDocument(LegalDocumentType::Terms);
    LegalDocumentVersion::factory()->for($document, 'document')->create(['version' => '1.1']);

    $this->actingAs(platformUser(Role::ComplianceOfficer))
        ->get(route('admin.legal-document-versions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/legal/versions')
            ->has('versions.data', 2)
            ->where('stats.versions', 2)
            ->where('stats.published', 1)
            ->where('stats.drafts', 1));
});

it('shows where each policy is accepted', function (): void {
    legalDocument(LegalDocumentType::Terms);

    $this->actingAs(platformUser())
        ->get(route('admin.legal-compliance-settings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/legal/compliance-settings')
            ->where('documents.0.slug', 'terms')
            ->where('documents.0.requires.registration', LegalDocumentType::Terms->isAcceptedAtRegistration())
            ->where('stats.documents', 1));
});

it('lists the accounts told about a policy change', function (): void {
    $document = legalDocument(LegalDocumentType::Terms);
    [, , $user] = clientWithWallet();

    app(LegalLibrary::class)->notifyClients($document->publishedVersion, platformUser());

    $this->actingAs(platformUser())
        ->get(route('admin.legal-notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/legal/notifications')
            ->has('notifications.data', 1)
            ->where('notifications.data.0.email', $user->email)
            ->where('notifications.data.0.acknowledged_at', null)
            ->where('stats.pending', 1));
});

it('reports acceptance coverage per policy', function (): void {
    legalDocument(LegalDocumentType::Terms);
    [, , $user] = clientWithWallet();

    app(LegalLibrary::class)->acceptOutstanding($user, LegalAcceptanceContext::OrganisationSettings);

    $this->actingAs(platformUser())
        ->get(route('admin.legal-compliance-reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/legal/reports')
            ->where('documents.0.accounts', 1)
            ->where('documents.0.coverage', 100)
            ->where('stats.acceptances', 1));
});

it('refuses the compliance screens to a client account', function (): void {
    [, , $user] = clientWithWallet();

    $this->actingAs($user)
        ->get(route('admin.legal-compliance-settings.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.legal-document-versions.index'))
        ->assertForbidden();
});

it('offers every seeded policy for correction in place', function (): void {
    publishLegalDocuments();

    $this->actingAs(platformUser())
        ->get(route('admin.legal-documents.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('documents', count(LegalDocumentType::cases()))
            ->where('documents.0.can_delete', true)
            ->where('documents.0.versions.0.can_edit', true)
            ->where('documents.0.versions.0.can_delete', true)
            ->where('documents.0.versions.0.body', fn (string $body): bool => trim($body) !== ''));

    $this->actingAs(platformUser())
        ->get(route('admin.legal-document-versions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('versions.data', count(LegalDocumentType::cases())));
});
