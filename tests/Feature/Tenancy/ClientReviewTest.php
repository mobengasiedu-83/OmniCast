<?php

use App\Enums\ClientRegistrationStatus;
use App\Enums\ClientService;
use App\Enums\ClientStatus;
use App\Enums\Role as RoleEnum;
use App\Mail\UserInvitationMail;
use App\Models\Client;
use App\Models\ClientEntitlement;
use App\Models\ClientRegistration;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    Mail::fake();

    $this->reviewer = platformUser(RoleEnum::ComplianceOfficer);

    $this->registration = ClientRegistration::factory()->create([
        'company_name' => 'Kente Logistics',
        'contact_name' => 'Kwame Boateng',
        'email' => 'kwame@kente.example.com',
        'services' => [ClientService::Sms->value],
    ]);
});

it('lists applications for reviewers', function (): void {
    $this->actingAs($this->reviewer)
        ->get(route('admin.client-registrations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/clients/registrations')
            ->has('registrations.data', 1)
            ->where('registrations.data.0.company_name', 'Kente Logistics')
            ->where('stats.pending', 1));
});

it('forbids the queue to client users', function (): void {
    [$client, , $user] = clientWithWallet();

    $this->actingAs($user)
        ->get(route('admin.client-registrations.index'))
        ->assertForbidden();
});

it('forbids platform users without the approve permission', function (): void {
    $operator = platformUser(RoleEnum::ClientOperator);

    $this->actingAs($operator)
        ->get(route('admin.client-registrations.index'))
        ->assertForbidden();

    $this->actingAs($operator)
        ->post(route('admin.client-registrations.approve', $this->registration))
        ->assertForbidden();
});

it('approves an application into a pending tenant and invites the applicant', function (): void {
    $this->actingAs($this->reviewer)
        ->post(route('admin.client-registrations.approve', $this->registration))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $client = Client::query()->where('name', 'Kente Logistics')->sole();

    expect($client->status)->toBe(ClientStatus::Pending)
        ->and($client->email)->toBe('kwame@kente.example.com')
        ->and($client->created_by)->toBe($this->reviewer->id)
        ->and($client->status_changed_at)->not->toBeNull()
        ->and($client->code)->toStartWith('KENTEL');

    $entitlements = ClientEntitlement::query()->where('client_id', $client->id)->get();

    expect($entitlements)->toHaveCount(count(ClientService::cases()))
        ->and($entitlements->firstWhere('service', ClientService::Sms)?->is_enabled)->toBeTrue()
        ->and($entitlements->firstWhere('service', ClientService::Voice)?->is_enabled)->toBeFalse();

    $registration = $this->registration->refresh();

    expect($registration->status)->toBe(ClientRegistrationStatus::Approved)
        ->and($registration->client_id)->toBe($client->id)
        ->and($registration->reviewed_by)->toBe($this->reviewer->id)
        ->and($registration->reviewed_at)->not->toBeNull();

    $invitation = UserInvitation::query()->where('email', 'kwame@kente.example.com')->sole();

    expect($invitation->role)->toBe(RoleEnum::ClientAdmin->value)
        ->and($invitation->client_id)->toBe($client->id);

    Mail::assertQueued(UserInvitationMail::class);

    expect(Activity::query()->where('description', 'approved client registration')->exists())->toBeTrue();
});

it('lets the reviewer override the tenant name and code', function (): void {
    $this->actingAs($this->reviewer)
        ->post(route('admin.client-registrations.approve', $this->registration), [
            'name' => 'Kente Logistics Group',
            'code' => 'KENTE-GH',
            'credit_limit' => 7500,
        ])
        ->assertSessionHasNoErrors();

    $client = Client::query()->where('code', 'KENTE-GH')->sole();

    expect($client->name)->toBe('Kente Logistics Group')
        ->and((float) $client->credit_limit)->toBe(7500.0);
});

it('rejects an application with a reason and creates no tenant', function (): void {
    $this->actingAs($this->reviewer)
        ->post(route('admin.client-registrations.reject', $this->registration), [
            'reason' => 'Company registration certificate is missing.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $registration = $this->registration->refresh();

    expect($registration->status)->toBe(ClientRegistrationStatus::Rejected)
        ->and($registration->rejection_reason)->toBe('Company registration certificate is missing.')
        ->and($registration->reviewed_by)->toBe($this->reviewer->id)
        ->and(Client::query()->count())->toBe(0)
        ->and(Activity::query()->where('description', 'rejected client registration')->exists())->toBeTrue();
});

it('requires a reason to reject an application', function (): void {
    $this->actingAs($this->reviewer)
        ->post(route('admin.client-registrations.reject', $this->registration), [])
        ->assertSessionHasErrors('reason');

    expect($this->registration->refresh()->status)->toBe(ClientRegistrationStatus::Pending);
});

it('does not decide the same application twice', function (): void {
    $this->actingAs($this->reviewer)
        ->post(route('admin.client-registrations.approve', $this->registration));

    $this->actingAs($this->reviewer)
        ->post(route('admin.client-registrations.reject', $this->registration), [
            'reason' => 'Already handled.',
        ])
        ->assertForbidden();

    expect(ClientRegistration::query()->count())->toBe(1)
        ->and(Client::query()->count())->toBe(1);
});
