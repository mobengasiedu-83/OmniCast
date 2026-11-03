<?php

use App\Enums\ClientAccountType;
use App\Enums\ClientDocumentStatus;
use App\Enums\ClientDocumentType;
use App\Enums\ClientService;
use App\Mail\ClientSignupOtpMail;
use App\Models\Client;
use App\Models\ClientRegistration;
use App\Models\ClientSignup;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function registrationPayload(array $overrides = []): array
{
    return [
        'account_type' => ClientAccountType::Business->value,
        'company_name' => 'Adinkra Foods',
        'contact_name' => 'Ama Mensah',
        'email' => 'ama@example.test',
        'phone' => '+233201234567',
        'country' => 'GH',
        'timezone' => 'Africa/Accra',
        'otp_channel' => 'email',
        'identification_type' => ClientDocumentType::BusinessRegistration->value,
        'identification_document' => UploadedFile::fake()->create('registration.pdf', 100, 'application/pdf'),
        'services' => [ClientService::Sms->value],
        ...$overrides,
    ];
}

beforeEach(function (): void {
    Storage::fake('local');
});

it('shows the onboarding form to guests', function (): void {
    $this->get(route('client-registration.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/register-client')
            ->has('services', count(ClientService::cases()))
            ->where('defaultCountry', 'GH')
            ->where('defaultTimezone', 'Africa/Accra'));
});

it('starts an email verified signup without creating the tenant before password setup', function (): void {
    Mail::fake();

    $this->post(route('client-registration.store'), registrationPayload())
        ->assertRedirect(route('client-registration.create'))
        ->assertSessionHasNoErrors();

    $signup = ClientSignup::query()->sole();

    expect($signup->email)->toBe('ama@example.test')
        ->and($signup->otp_channel)->toBe('email')
        ->and($signup->registration_data['services'])->toBe(['sms'])
        ->and($signup->registration_data['identification_document']['type'])->toBe('business_registration')
        ->and(Client::query()->count())->toBe(0)
        ->and(ClientRegistration::query()->count())->toBe(0);

    Storage::disk('local')->assertExists($signup->registration_data['identification_document']['path']);

    Mail::assertSent(ClientSignupOtpMail::class);
});

it('completes email otp signup and marks only the email verified', function (): void {
    Mail::fake();
    $this->post(route('client-registration.store'), registrationPayload())
        ->assertRedirect(route('client-registration.create'));

    $code = null;
    Mail::assertSent(ClientSignupOtpMail::class, function (ClientSignupOtpMail $mail) use (&$code): bool {
        $code = $mail->code;

        return true;
    });

    $this->post(route('client-registration.verify'), ['otp' => $code])
        ->assertRedirect(route('client-registration.create'));
    $this->post(route('client-registration.password'), [
        'password' => 'StrongPass123!',
        'password_confirmation' => 'StrongPass123!',
    ])->assertRedirect(route('login'));

    $user = User::query()->sole();
    expect(Hash::check('StrongPass123!', $user->password))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->phone_verified_at)->toBeNull()
        ->and(Client::query()->sole()->status->value)->toBe('pending')
        ->and(Client::query()->sole()->documents()->sole()->type)->toBe(ClientDocumentType::BusinessRegistration)
        ->and(Client::query()->sole()->documents()->sole()->status)->toBe(ClientDocumentStatus::Pending);

    $this->post(route('login.store'), [
        'email' => 'ama@example.test',
        'password' => 'StrongPass123!',
    ])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

it('completes sms otp signup and marks only the phone verified', function (): void {
    config([
        'services.sms.driver' => 'txtconnect',
        'services.sms.endpoint' => 'https://txtconnect.test/send',
        'services.sms.token' => 'test-token',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://txtconnect.test/send' => Http::response([
            'data' => ['status_code' => '000', 'in_error' => false],
        ], 200),
    ]);

    $this->post(route('client-registration.store'), registrationPayload(['otp_channel' => 'sms']))
        ->assertRedirect(route('client-registration.create'));

    $signup = ClientSignup::query()->sole();
    $providerRequest = Http::recorded()->first()[0];
    preg_match('/(\d{6})/', (string) $providerRequest['sms'], $matches);
    $code = $matches[1] ?? '';

    $this->post(route('client-registration.verify'), ['otp' => $code])
        ->assertRedirect(route('client-registration.create'));
    $this->post(route('client-registration.password'), [
        'password' => 'StrongPass123!',
        'password_confirmation' => 'StrongPass123!',
    ])->assertRedirect(route('login'));

    $user = User::query()->sole();
    expect($signup->otp_channel)->toBe('sms')
        ->and($user->phone_verified_at)->not->toBeNull()
        ->and($user->email_verified_at)->toBeNull();

    Http::assertSent(fn ($request): bool => $request['to'] === '233201234567');
    $this->post(route('login.store'), [
        'email' => '+233201234567',
        'password' => 'StrongPass123!',
    ])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

it('validates required application and verification details', function (): void {
    $this->post(route('client-registration.store'), [])
        ->assertSessionHasErrors([
            'account_type', 'contact_name', 'email', 'phone', 'country', 'timezone', 'otp_channel',
            'identification_type', 'identification_document', 'services',
        ]);

    expect(ClientSignup::query()->count())->toBe(0)
        ->and(ClientRegistration::query()->count())->toBe(0);
});

it('requires a business name for business accounts', function (): void {
    $this->post(route('client-registration.store'), registrationPayload(['company_name' => null]))
        ->assertSessionHasErrors('company_name');

    expect(ClientSignup::query()->count())->toBe(0);
});

it('accepts private accounts without a business name and requires one private identification document', function (): void {
    Mail::fake();

    $this->post(route('client-registration.store'), registrationPayload([
        'account_type' => ClientAccountType::Private->value,
        'company_name' => 'Should not be saved as a business name',
        'identification_type' => ClientDocumentType::IdDocument->value,
    ]))->assertRedirect(route('client-registration.create'))->assertSessionHasNoErrors();

    $signup = ClientSignup::query()->sole();
    expect($signup->registration_data['account_type'])->toBe(ClientAccountType::Private->value)
        ->and($signup->registration_data['company_name'])->toBeNull();

    $this->post(route('client-registration.verify'), ['otp' => (function () {
        $code = null;
        Mail::assertSent(ClientSignupOtpMail::class, function (ClientSignupOtpMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });

        return $code;
    })()])->assertRedirect(route('client-registration.create'));
    $this->post(route('client-registration.password'), [
        'password' => 'StrongPass123!',
        'password_confirmation' => 'StrongPass123!',
    ])->assertRedirect(route('login'));

    $client = Client::query()->sole();
    expect($client->accountType())->toBe(ClientAccountType::Private)
        ->and($client->name)->toBe('Ama Mensah')
        ->and($client->documents()->sole()->type)->toBe(ClientDocumentType::IdDocument);
});

it('rejects an identification document that does not match the selected account type', function (): void {
    $this->post(route('client-registration.store'), registrationPayload([
        'account_type' => ClientAccountType::Private->value,
        'company_name' => null,
        'identification_type' => ClientDocumentType::TaxCertificate->value,
    ]))->assertSessionHasErrors('identification_type');

    expect(ClientSignup::query()->count())->toBe(0);
});

it('accepts only one open application per email address', function (): void {
    ClientRegistration::factory()->create(['email' => 'duplicate@example.com']);

    $this->post(route('client-registration.store'), registrationPayload([
        'company_name' => 'Duplicate Ltd',
        'email' => 'Duplicate@Example.com',
    ]))->assertSessionHasErrors('email');

    expect(ClientRegistration::query()->count())->toBe(1)
        ->and(ClientSignup::query()->count())->toBe(0);
});

it('lets a previously rejected applicant apply again', function (): void {
    Mail::fake();
    ClientRegistration::factory()->rejected()->create(['email' => 'retry@example.com']);

    $this->post(route('client-registration.store'), registrationPayload([
        'company_name' => 'Retry Ltd',
        'contact_name' => 'Yaa Asantewaa',
        'email' => 'retry@example.com',
        'phone' => '+233209998887',
    ]))->assertSessionHasNoErrors();

    expect(ClientSignup::query()->count())->toBe(1)
        ->and(ClientRegistration::query()->count())->toBe(1);
});

it('does not accept applications from signed in users', function (): void {
    $this->actingAs(platformUser())
        ->post(route('client-registration.store'), registrationPayload())
        ->assertRedirect();

    expect(ClientSignup::query()->count())->toBe(0);
});
