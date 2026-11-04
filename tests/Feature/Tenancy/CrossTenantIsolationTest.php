<?php

use App\Enums\Channel;
use App\Enums\ClientDocumentType;
use App\Enums\Role as RoleEnum;
use App\Models\ClientDocument;
use App\Models\Contact;
use App\Models\SenderId;
use App\Models\UserInvitation;
use App\Services\CampaignService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    [$this->clientA, $this->walletA, $this->userA] = clientWithWallet(balance: 120.0);
    [$this->clientB, $this->walletB, $this->userB] = clientWithWallet(balance: 900.0);

    $this->contactB = Contact::factory()->forClient($this->clientB)->create([
        'first_name' => 'Tenant',
        'last_name' => 'Bravo',
        'phone' => '+233200000001',
    ]);

    $this->senderB = SenderId::factory()->for($this->clientB)->create(['name' => 'TENANTB']);

    $this->campaignB = app(CampaignService::class)->create([
        'client_id' => $this->clientB->id,
        'name' => 'Tenant B campaign',
        'channel' => Channel::Sms,
        'sms_message' => 'Private to tenant B.',
        'schedule_type' => 'now',
    ], $this->userB);

    $this->invitationB = UserInvitation::issue([
        'email' => 'intruder@tenantib.example.com',
        'role' => RoleEnum::ClientOperator,
        'client_id' => $this->clientB->id,
    ])['invitation'];
});

it('hides another tenant campaign from the directory and the detail page', function (): void {
    $this->actingAs($this->userA)
        ->get(route('campaigns.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('campaigns.data', 0)
            ->where('campaigns.total', 0));

    $this->actingAs($this->userA)
        ->get(route('campaigns.show', $this->campaignB))
        ->assertForbidden();

    $this->actingAs($this->userA)
        ->get(route('campaigns.edit', $this->campaignB))
        ->assertForbidden();
});

it('refuses to update or delete another tenant campaign', function (): void {
    $this->actingAs($this->userA)
        ->put(route('campaigns.update', $this->campaignB), [
            'name' => 'Hijacked',
            'channel' => 'sms',
            'sms_message' => 'Hijacked body.',
            'schedule_type' => 'now',
        ])
        ->assertForbidden();

    $this->actingAs($this->userA)
        ->delete(route('campaigns.destroy', $this->campaignB))
        ->assertForbidden();

    expect($this->campaignB->refresh()->name)->toBe('Tenant B campaign');
});

it('refuses to read or edit another tenant contact', function (): void {
    $this->actingAs($this->userA)
        ->get(route('contacts.edit', $this->contactB))
        ->assertForbidden();

    $this->actingAs($this->userA)
        ->put(route('contacts.update', $this->contactB), [
            'first_name' => 'Hijacked',
            'phone' => '+233200000002',
        ])
        ->assertForbidden();

    expect($this->contactB->refresh()->first_name)->toBe('Tenant');
});

it('keeps the wallet ledger on the signed-in tenant', function (): void {
    $this->actingAs($this->userA)
        ->withoutVite()
        ->get(route('wallet.index', ['client_id' => $this->clientB->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('wallet.id', $this->walletA->id)
            ->where('wallet.balance', 120));
});

it('does not leak another tenant invitation into the team screen', function (): void {
    $this->actingAs($this->userA)
        ->get(route('team.index'))
        ->assertOk()
        ->assertDontSee('intruder@tenantib.example.com');
});

it('blocks client users from every platform administration screen', function (): void {
    $this->actingAs($this->userA)->get(route('admin.clients.index'))->assertForbidden();
    $this->actingAs($this->userA)->get(route('admin.clients.show', $this->clientB))->assertForbidden();
    $this->actingAs($this->userA)->get(route('admin.client-registrations.index'))->assertForbidden();
    $this->actingAs($this->userA)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($this->userA)->get(route('admin.invitations.index'))->assertForbidden();
});

it('returns only the signed-in users own tenant over the api', function (): void {
    $token = $this->userA->createToken('tenant-a-reporting', ['read'])->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson(route('api.v1.me'))
        ->assertOk()
        ->assertJsonPath('data.client_id', $this->clientA->id)
        ->assertJsonPath('data.email', $this->userA->email);
});

it('lets the platform team see every tenant', function (): void {
    $this->actingAs(platformUser(RoleEnum::SuperAdmin))
        ->get(route('admin.clients.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('clients.data', 2)
            ->where('stats.total', 2));
});

it('keeps organisation documents and profile updates inside the signed-in tenant', function (): void {
    Storage::fake('local');
    $otherDocument = ClientDocument::factory()->for($this->clientB)->create(['title' => 'Tenant B private document']);
    $this->actingAs($this->userA)
        ->post(route('organisation-settings.documents.store'), [
            'type' => ClientDocumentType::BusinessRegistration->value,
            'document' => UploadedFile::fake()->create('registration.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $document = ClientDocument::query()->where('client_id', $this->clientA->id)->sole();
    expect($document->client_id)->toBe($this->clientA->id);
    Storage::disk('local')->assertExists($document->path);

    $this->actingAs($this->userA)
        ->put(route('organisation-settings.update'), [
            'client_id' => $this->clientB->id,
            'name' => 'Tenant A Updated',
            'email' => 'a@example.test',
            'phone' => '+233200000003',
            'timezone' => 'Africa/Accra',
            'locale' => 'en',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($this->clientA->refresh()->name)->toBe('Tenant A Updated')
        ->and($this->clientB->refresh()->name)->not->toBe('Tenant A Updated');

    $this->actingAs($this->userA)
        ->get(route('organisation-settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('documents', 1)
            ->where('documents.0.title', 'Business registration')
            ->where('organisation.name', 'Tenant A Updated'));
});

it('refuses to approve another tenant sender id', function (): void {
    $this->actingAs($this->userA)
        ->post(route('sender-ids.approve', $this->senderB))
        ->assertForbidden();

    expect($this->senderB->refresh()->status->value)->toBe('pending');

    $this->actingAs($this->userA)
        ->withoutVite()
        ->get(route('sender-ids.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('senderIds.total', 0));
});
