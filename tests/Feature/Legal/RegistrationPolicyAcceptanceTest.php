<?php

use App\Enums\ClientAccountType;
use App\Enums\ClientDocumentType;
use App\Enums\ClientService;
use App\Enums\LegalAcceptanceContext;
use App\Mail\ClientSignupOtpMail;
use App\Models\Client;
use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The public application form, with the policy acknowledgements ticked.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function legalRegistrationPayload(array $overrides = []): array
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
        'accept_terms' => true,
        'accept_privacy' => true,
        'accept_acceptable-use' => true,
        ...$overrides,
    ];
}

beforeEach(function (): void {
    $this->withoutVite();
    Storage::fake('local');
    Mail::fake();

    publishLegalDocuments();
});

it('offers the registration policies to the applicant', function (): void {
    $this->get(route('client-registration.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/register-client')
            ->has('legalDocuments', 3)
            ->where('legalDocuments', fn (Collection $documents): bool => $documents
                ->pluck('slug')
                ->sort()
                ->values()
                ->all() === ['acceptable-use', 'privacy', 'terms']));
});

it('refuses an application that does not accept the policies', function (): void {
    $this->post(route('client-registration.store'), legalRegistrationPayload([
        'accept_terms' => false,
        'accept_privacy' => false,
        'accept_acceptable-use' => false,
    ]))->assertSessionHasErrors(['accept_terms', 'accept_privacy', 'accept_acceptable-use']);

    expect(Client::query()->count())->toBe(0);
});

it('writes an acceptance for each policy when the account is created', function (): void {
    $this->post(route('client-registration.store'), legalRegistrationPayload())
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
    $acceptances = LegalAcceptance::query()->where('user_id', $user->id)->get();
    $terms = LegalDocument::query()->where('slug', 'terms')->sole();

    expect($acceptances)->toHaveCount(3)
        ->and($acceptances->every(fn (LegalAcceptance $acceptance): bool => $acceptance->context === LegalAcceptanceContext::Registration))->toBeTrue()
        ->and($acceptances->every(fn (LegalAcceptance $acceptance): bool => $acceptance->client_id === $user->client_id))->toBeTrue()
        ->and($acceptances->contains(fn (LegalAcceptance $acceptance): bool => $acceptance->legal_document_version_id === $terms->publishedVersion->id))->toBeTrue();
});
