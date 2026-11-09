<?php

use App\Enums\LegalAcceptanceContext;
use App\Enums\LegalDocumentType;
use App\Enums\OrganisationDpaStatus;
use App\Enums\Role;
use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\LegalNotification;
use App\Models\OrganisationDpa;
use App\Models\User;
use App\Services\Legal\LegalLibrary;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();

    [$this->client, , $this->user] = clientWithWallet();
    publishLegalDocuments();
});

it('redirects a guest to the login screen', function (): void {
    $this->get(route('legal.settings.index'))->assertRedirect(route('login'));
});

it('shows the organisation every published policy', function (): void {
    $this->actingAs($this->user)
        ->get(route('legal.settings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/legal')
            ->has('documents', count(LegalDocumentType::cases()))
            ->where('outstanding', count(LegalDocumentType::cases()))
            ->where('documents.0.accepted', false)
            ->where('dpa.status', OrganisationDpaStatus::NotStarted->value)
            ->has('history', 0));
});

it('records an acceptance for every outstanding policy', function (): void {
    $this->actingAs($this->user)
        ->post(route('legal.settings.accept'))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $acceptances = LegalAcceptance::query()->where('user_id', $this->user->id)->get();

    expect($acceptances)->toHaveCount(count(LegalDocumentType::cases()))
        ->and($acceptances->every(fn (LegalAcceptance $acceptance): bool => $acceptance->context === LegalAcceptanceContext::OrganisationSettings))->toBeTrue()
        ->and($acceptances->every(fn (LegalAcceptance $acceptance): bool => $acceptance->client_id === $this->client->id))->toBeTrue()
        ->and($acceptances->first()->ip_address)->not->toBeNull();
});

it('treats a teammate acceptance as the organisation acceptance', function (): void {
    $teammate = User::factory()->forClient($this->client)->create();
    $teammate->syncRoles([Role::ClientAdmin->value]);

    app(LegalLibrary::class)->acceptOutstanding($teammate, LegalAcceptanceContext::OrganisationSettings);

    $this->actingAs($this->user)
        ->get(route('legal.settings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('outstanding', 0)
            ->where('documents.0.accepted', true)
            ->where('documents.0.accepted_by', $teammate->name));
});

it('does not duplicate an acceptance on a second click', function (): void {
    $this->actingAs($this->user)->post(route('legal.settings.accept'));
    $this->actingAs($this->user)->post(route('legal.settings.accept'));

    expect(LegalAcceptance::query()->where('user_id', $this->user->id)->count())
        ->toBe(count(LegalDocumentType::cases()));
});

it('notifies the platform team when the organisation asks for a DPA', function (): void {
    $manager = platformUser();

    $this->actingAs($this->user)
        ->post(route('legal.settings.dpa'))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $dpa = OrganisationDpa::query()->where('client_id', $this->client->id)->sole();

    expect($dpa->status)->toBe(OrganisationDpaStatus::Requested)
        ->and($dpa->signatory_email)->toBe($this->user->email)
        ->and($manager->fresh()->notifications()->count())->toBe(1);
});

it('marks an announcement as acknowledged when the policy is accepted', function (): void {
    $terms = LegalDocument::query()->where('slug', LegalDocumentType::Terms->value)->sole();
    app(LegalLibrary::class)->notifyClients($terms->publishedVersion, platformUser());

    expect(LegalNotification::query()->where('user_id', $this->user->id)->whereNull('acknowledged_at')->count())->toBe(1);

    $this->actingAs($this->user)->post(route('legal.settings.accept'));

    expect(LegalNotification::query()->where('user_id', $this->user->id)->whereNull('acknowledged_at')->count())->toBe(0);
});
