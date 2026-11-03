<?php

use App\Enums\ClientRegistrationStatus;
use App\Enums\ClientService;
use App\Models\Client;
use App\Models\ClientRegistration;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the onboarding form to guests', function (): void {
    $this->get(route('client-registration.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/register-client')
            ->has('services', count(ClientService::cases()))
            ->where('defaultTimezone', config('app.timezone')));
});

it('opens a pending application without creating a tenant', function (): void {
    $this->post(route('client-registration.store'), [
        'company_name' => 'Adinkra Foods',
        'contact_name' => 'Ama Mensah',
        'email' => 'Ama.Mensah@Example.com',
        'phone' => '+233201234567',
        'country' => 'Ghana',
        'timezone' => 'Africa/Accra',
        'website' => 'https://adinkra.example.com',
        'services' => [ClientService::Sms->value, ClientService::Voice->value],
        'expected_monthly_volume' => 25000,
        'message' => 'We send order updates.',
    ])
        ->assertRedirect(route('home'))
        ->assertSessionHasNoErrors();

    $registration = ClientRegistration::query()->sole();

    expect($registration->status)->toBe(ClientRegistrationStatus::Pending)
        ->and($registration->email)->toBe('ama.mensah@example.com')
        ->and($registration->services)->toBe(['sms', 'voice'])
        ->and($registration->requestedServices())->toBe([ClientService::Sms, ClientService::Voice])
        ->and($registration->ip_address)->not->toBeNull()
        ->and(Client::query()->count())->toBe(0);
});

it('validates the application payload', function (): void {
    $this->post(route('client-registration.store'), [])
        ->assertSessionHasErrors([
            'company_name',
            'contact_name',
            'email',
            'phone',
            'timezone',
            'services',
        ]);

    expect(ClientRegistration::query()->count())->toBe(0);
});

it('accepts only one open application per email address', function (): void {
    ClientRegistration::factory()->create(['email' => 'duplicate@example.com']);

    $this->post(route('client-registration.store'), [
        'company_name' => 'Duplicate Ltd',
        'contact_name' => 'Kofi Owusu',
        'email' => 'Duplicate@Example.com',
        'phone' => '+233201112223',
        'timezone' => 'Africa/Accra',
        'services' => [ClientService::Sms->value],
    ])->assertSessionHasErrors('email');

    expect(ClientRegistration::query()->count())->toBe(1);
});

it('lets a previously rejected applicant apply again', function (): void {
    ClientRegistration::factory()->rejected()->create(['email' => 'retry@example.com']);

    $this->post(route('client-registration.store'), [
        'company_name' => 'Retry Ltd',
        'contact_name' => 'Yaa Asantewaa',
        'email' => 'retry@example.com',
        'phone' => '+233209998887',
        'timezone' => 'Africa/Accra',
        'services' => [ClientService::Sms->value],
    ])->assertSessionHasNoErrors();

    expect(ClientRegistration::query()->count())->toBe(2);
});

it('does not accept applications from signed-in users', function (): void {
    $this->actingAs(platformUser())
        ->post(route('client-registration.store'), [
            'company_name' => 'Signed In Ltd',
            'contact_name' => 'Nii Armah',
            'email' => 'signed@example.com',
            'phone' => '+233207776665',
            'timezone' => 'Africa/Accra',
            'services' => [ClientService::Sms->value],
        ])
        ->assertRedirect();

    expect(ClientRegistration::query()->count())->toBe(0);
});
