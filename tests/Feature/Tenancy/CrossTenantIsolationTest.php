<?php

use App\Enums\Channel;
use App\Enums\Role as RoleEnum;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\SenderId;
use App\Models\UserInvitation;
use App\Services\CampaignService;
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
        ->get(route('wallet.index', ['client_id' => $this->clientB->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('wallet.id', $this->walletA->id)
            ->where('wallet.balance', 120.0));
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

it('refuses to approve another tenant sender id', function (): void {
    $this->actingAs($this->userA)
        ->post(route('sender-ids.approve', $this->senderB))
        ->assertForbidden();

    expect($this->senderB->refresh()->status->value)->toBe('pending');

    $this->actingAs($this->userA)
        ->get(route('sender-ids.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('senderIds', 0));
});
