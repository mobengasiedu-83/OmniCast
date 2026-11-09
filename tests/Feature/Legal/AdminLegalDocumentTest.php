<?php

use App\Enums\LegalAcceptanceContext;
use App\Enums\LegalDocumentStatus;
use App\Enums\LegalDocumentType;
use App\Enums\Role;
use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use App\Models\LegalNotification;
use App\Services\Legal\LegalLibrary;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

it('shows the document register to the compliance team', function (): void {
    legalDocument(LegalDocumentType::Terms);

    $this->actingAs(platformUser(Role::ComplianceOfficer))
        ->get(route('admin.legal-documents.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/legal/documents')
            ->has('documents', 1)
            ->where('documents.0.slug', 'terms')
            ->where('documents.0.published.version', '1.0')
            ->where('stats.documents', 1)
            ->where('stats.published', 1));
});

it('refuses the register to a client account', function (): void {
    [, , $user] = clientWithWallet();

    $this->actingAs($user)
        ->get(route('admin.legal-documents.index'))
        ->assertForbidden();
});

it('creates a document with its acceptance rules', function (): void {
    $this->actingAs(platformUser())
        ->post(route('admin.legal-documents.store'), [
            'slug' => 'Cookie Policy',
            'title' => 'Cookie Policy',
            'summary' => 'How cookies are used.',
            'sort_order' => 6,
            'is_active' => true,
            'requires_acceptance_at_registration' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $document = LegalDocument::query()->sole();

    expect($document->slug)->toBe('cookie-policy')
        ->and($document->requires_acceptance_at_registration)->toBeTrue()
        ->and($document->requires_acceptance_on_campaign_submission)->toBeFalse()
        ->and($document->is_active)->toBeTrue();
});

it('keeps a new version as a draft until it is published', function (): void {
    $document = legalDocument(LegalDocumentType::Terms);

    $this->actingAs(platformUser())
        ->post(route('admin.legal-documents.versions.store', $document), [
            'version' => '1.1',
            'body' => str_repeat('A longer clause for the next revision. ', 4),
            'change_summary' => 'Clarified the notice period.',
            'requires_reacceptance' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $draft = LegalDocumentVersion::query()->where('version', '1.1')->sole();

    expect($draft->status)->toBe(LegalDocumentStatus::Draft)
        ->and($document->fresh()->publishedVersion->version)->toBe('1.0');
});

it('archives the live version when a draft is published', function (): void {
    $document = legalDocument(LegalDocumentType::Terms);
    $draft = LegalDocumentVersion::factory()->for($document, 'document')->create(['version' => '1.1']);

    $this->actingAs(platformUser())
        ->post(route('admin.legal-document-versions.publish', $draft))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($draft->fresh()->status)->toBe(LegalDocumentStatus::Published)
        ->and($document->versions()->where('version', '1.0')->sole()->status)->toBe(LegalDocumentStatus::Archived)
        ->and($document->fresh()->publishedVersion->version)->toBe('1.1');
});

it('announces a published version to every client account', function (): void {
    $document = legalDocument(LegalDocumentType::Terms);
    [, , $first] = clientWithWallet();
    [$secondClient, , $second] = clientWithWallet();
    $actor = platformUser();

    $this->actingAs($actor)
        ->post(route('admin.legal-document-versions.notify', $document->publishedVersion))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(LegalNotification::query()->count())->toBe(2)
        ->and($first->fresh()->notifications()->count())->toBe(1)
        ->and($second->fresh()->notifications()->count())->toBe(1)
        ->and($document->publishedVersion->fresh()->notified_at)->not->toBeNull()
        ->and(LegalNotification::query()->where('client_id', $secondClient->id)->exists())->toBeTrue();
});

it('records the version a customer accepted when a new one is published', function (): void {
    $document = legalDocument(LegalDocumentType::Terms);
    [, , $user] = clientWithWallet();

    app(LegalLibrary::class)->acceptOutstanding($user, LegalAcceptanceContext::OrganisationSettings);

    $draft = LegalDocumentVersion::factory()->for($document, 'document')->create(['version' => '2.0']);
    $draft->publish(platformUser());

    expect(LegalAcceptance::query()->where('user_id', $user->id)->sole()->legal_document_version_id)
        ->toBe($document->versions()->where('version', '1.0')->sole()->id);
});

it('edits the wording of a version nobody has relied on yet', function (): void {
    $document = legalDocument(LegalDocumentType::Terms);
    $version = $document->publishedVersion;

    $this->actingAs(platformUser())
        ->put(route('admin.legal-document-versions.update', $version), [
            'version' => '1.1',
            'body' => 'Corrected wording that replaces the seeded draft text entirely.',
            'change_summary' => 'Fixed the notice period.',
            'effective_on' => '2026-11-09',
            'requires_reacceptance' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $version->refresh();

    expect($version->version)->toBe('1.1')
        ->and($version->body)->toBe('Corrected wording that replaces the seeded draft text entirely.')
        ->and($version->status)->toBe(LegalDocumentStatus::Published)
        ->and($document->fresh()->publishedVersion->version)->toBe('1.1');
});

it('refuses to edit a version a customer has already accepted', function (): void {
    $document = legalDocument(LegalDocumentType::Terms);
    $version = $document->publishedVersion;
    [, , $user] = clientWithWallet();

    app(LegalLibrary::class)->acceptOutstanding($user, LegalAcceptanceContext::OrganisationSettings);

    $original = $version->body;

    $this->actingAs(platformUser())
        ->put(route('admin.legal-document-versions.update', $version), [
            'version' => '1.0',
            'body' => 'A rewrite that would silently change what the customer agreed to.',
            'requires_reacceptance' => true,
        ])
        ->assertRedirect();

    expect($version->fresh()->body)->toBe($original);
});

it('deletes a version nothing depends on', function (): void {
    $document = legalDocument(LegalDocumentType::Terms);
    $draft = LegalDocumentVersion::factory()->for($document, 'document')->create(['version' => '1.1']);

    $this->actingAs(platformUser())
        ->delete(route('admin.legal-document-versions.destroy', $draft))
        ->assertRedirect();

    expect(LegalDocumentVersion::query()->whereKey($draft->id)->exists())->toBeFalse();
});

it('refuses to delete a version that was accepted', function (): void {
    $document = legalDocument(LegalDocumentType::Terms);
    [, , $user] = clientWithWallet();

    app(LegalLibrary::class)->acceptOutstanding($user, LegalAcceptanceContext::OrganisationSettings);

    $this->actingAs(platformUser())
        ->delete(route('admin.legal-document-versions.destroy', $document->publishedVersion))
        ->assertRedirect();

    expect($document->versions()->where('version', '1.0')->exists())->toBeTrue();
});

it('deletes a document nobody has accepted', function (): void {
    $document = legalDocument(LegalDocumentType::Terms);

    $this->actingAs(platformUser())
        ->delete(route('admin.legal-documents.destroy', $document))
        ->assertRedirect();

    expect(LegalDocument::query()->whereKey($document->id)->exists())->toBeFalse()
        ->and(LegalDocumentVersion::query()->where('legal_document_id', $document->id)->exists())->toBeFalse();
});

it('refuses to delete a document with acceptances on record', function (): void {
    $document = legalDocument(LegalDocumentType::Terms);
    [, , $user] = clientWithWallet();

    app(LegalLibrary::class)->acceptOutstanding($user, LegalAcceptanceContext::OrganisationSettings);

    $this->actingAs(platformUser())
        ->delete(route('admin.legal-documents.destroy', $document))
        ->assertRedirect();

    expect(LegalDocument::query()->whereKey($document->id)->exists())->toBeTrue();
});

it('changes where a policy is accepted', function (): void {
    $document = legalDocument(LegalDocumentType::AcceptableUse);

    $this->actingAs(platformUser())
        ->patch(route('admin.legal-documents.requirements', $document), [
            'requires_acceptance_at_registration' => false,
            'requires_acceptance_on_campaign_submission' => true,
            'requires_acceptance_on_wallet_top_up' => false,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $document->refresh();

    expect($document->requires_acceptance_at_registration)->toBeFalse()
        ->and($document->requires_acceptance_on_campaign_submission)->toBeTrue()
        ->and($document->requires_acceptance_on_wallet_top_up)->toBeFalse();
});
