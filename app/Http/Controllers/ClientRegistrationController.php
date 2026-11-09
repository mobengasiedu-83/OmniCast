<?php

namespace App\Http\Controllers;

use App\Concerns\PasswordValidationRules;
use App\Enums\ClientAccountType;
use App\Enums\ClientDocumentStatus;
use App\Enums\ClientDocumentType;
use App\Enums\ClientRegistrationStatus;
use App\Enums\ClientService;
use App\Enums\ClientStatus;
use App\Enums\LegalAcceptanceContext;
use App\Enums\Role;
use App\Http\Requests\Clients\RegisterClientRequest;
use App\Mail\ClientSignupOtpMail;
use App\Models\Client;
use App\Models\ClientEntitlement;
use App\Models\ClientRegistration;
use App\Models\ClientSignup;
use App\Models\LegalDocument;
use App\Models\User;
use App\Services\CountryCatalog;
use App\Services\Legal\LegalLibrary;
use App\Services\Notifications\SystemNotifier;
use App\Services\OtpSmsSender;
use App\Services\PlatformConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/** Public client application and email verification flow. */
class ClientRegistrationController extends Controller
{
    use PasswordValidationRules;

    public function __construct(
        protected SystemNotifier $notifications,
        protected LegalLibrary $legal,
    ) {}

    public function create(Request $request, CountryCatalog $countries, PlatformConfiguration $configuration): Response
    {
        $signup = $this->currentSignup($request);
        if ($signup && (
            ($signup->verified_at === null && ($signup->otp_expires_at->isPast() || $signup->attempts >= 5))
            || ($signup->verified_at?->isBefore(now()->subMinutes(30)) ?? false)
        )) {
            $this->deleteSignup($signup);
            $request->session()->forget('client_signup_id');
            $signup = null;
        }
        $defaultTimezoneSetting = (string) $configuration->resolve('company', 'default_timezone', config('app.timezone'));
        $defaultCountry = $countries->defaultCountry($defaultTimezoneSetting);
        $countryTimezones = $countries->timezones($defaultCountry);
        $defaultTimezone = in_array($defaultTimezoneSetting, $countryTimezones, true)
            ? $defaultTimezoneSetting
            : ($countryTimezones[0] ?? $defaultTimezoneSetting);

        return Inertia::render('auth/register-client', [
            'step' => $signup?->verified_at ? 'password' : ($signup ? 'otp' : 'details'),
            'email' => $signup?->email,
            'otpChannel' => $signup?->otp_channel,
            'services' => array_map(fn (ClientService $service): array => [
                'value' => $service->value, 'label' => $service->label(),
            ], ClientService::cases()),
            'accountTypes' => array_map(fn (ClientAccountType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'identificationTypes' => array_map(fn (ClientDocumentType $documentType): array => [
                    'value' => $documentType->value,
                    'label' => $documentType->label(),
                ], $type->identificationTypes()),
            ], ClientAccountType::cases()),
            'defaultTimezone' => $defaultTimezone,
            'countries' => $countries->all(),
            'defaultCountry' => $defaultCountry,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'legalDocuments' => $this->legal->registrationDocuments(),
        ]);
    }

    public function store(RegisterClientRequest $request, OtpSmsSender $sms): RedirectResponse
    {
        ClientSignup::query()->where('created_at', '<', now()->subDay())->get()->each(
            fn (ClientSignup $signup) => $this->deleteSignup($signup),
        );
        $attributes = $request->registrationAttributes();
        $attributes['legal_acceptances'] = $request->acceptedLegalSlugs();
        $previousSignup = $this->currentSignup($request);
        if ($previousSignup !== null) {
            $this->deleteSignup($previousSignup);
        }

        $document = $request->file('identification_document');
        abort_unless($document !== null, 422);
        $disk = (string) app(PlatformConfiguration::class)->resolve('storage', 'client_documents_disk', 'local');
        $diskConfiguration = config('filesystems.disks.'.$disk);
        abort_unless(is_array($diskConfiguration) && ($diskConfiguration['visibility'] ?? 'private') !== 'public', 422);
        $documentPath = $document->store('client-signups', $disk);
        $attributes['identification_document'] = [
            'type' => $request->validated('identification_type'),
            'title' => ClientDocumentType::from($request->validated('identification_type'))->label(),
            'path' => $documentPath,
            'storage_disk' => $disk,
            'original_name' => $document->getClientOriginalName(),
            'mime_type' => $document->getClientMimeType(),
            'size_bytes' => $document->getSize(),
        ];
        $code = (string) random_int(100000, 999999);
        $signup = ClientSignup::query()->create([
            'email' => $attributes['email'],
            'phone' => $attributes['phone'],
            'otp_channel' => $request->validated('otp_channel'),
            'registration_data' => $attributes,
            'otp_hash' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        $request->session()->put('client_signup_id', $signup->id);
        if ($signup->otp_channel === 'sms') {
            if (! $sms->send($signup->phone, $code, $signup->id)) {
                $this->deleteSignup($signup);
                $request->session()->forget('client_signup_id');
                throw ValidationException::withMessages([
                    'otp_channel' => 'SMS delivery is not configured or the provider rejected the message. Choose email or contact support.',
                ]);
            }
        } else {
            try {
                Mail::to($signup->email)->send(new ClientSignupOtpMail($code, $attributes['contact_name']));
                Log::channel('email')->info('Client signup verification email was accepted by the mail transport.', [
                    'signup_id' => $signup->id,
                ]);
            } catch (Throwable $exception) {
                Log::channel('email')->error('Client signup verification email delivery failed.', [
                    'signup_id' => $signup->id,
                    'exception' => $exception::class,
                ]);

                $this->deleteSignup($signup);

                throw $exception;
            }
        }

        return to_route('client-registration.create')->with('toast', [
            'type' => 'success',
            'message' => $signup->otp_channel === 'sms'
                ? 'We texted you a verification code. It expires in 10 minutes.'
                : 'We emailed you a verification code. It expires in 10 minutes.',
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['otp' => ['required', 'digits:6']]);
        $signup = $this->currentSignup($request);
        abort_unless($signup && ! $signup->verified_at, 404);

        if ($signup->otp_expires_at->isPast() || $signup->attempts >= 5) {
            throw ValidationException::withMessages(['otp' => 'This code has expired. Submit your details again to receive a new one.']);
        }

        $signup->increment('attempts');
        if (! Hash::check((string) $request->input('otp'), $signup->otp_hash)) {
            throw ValidationException::withMessages(['otp' => 'That verification code is incorrect.']);
        }

        $signup->forceFill(['verified_at' => now()])->save();

        return to_route('client-registration.create')->with('toast', [
            'type' => 'success',
            'message' => $signup->otp_channel === 'sms'
                ? 'Phone number verified. Create a password to finish your account.'
                : 'Email verified. Create a password to finish your account.',
        ]);
    }

    public function setPassword(Request $request): RedirectResponse
    {
        $signup = $this->currentSignup($request);
        abort_unless($signup?->verified_at && $signup->verified_at->isAfter(now()->subMinutes(30)), 403);
        $validated = $request->validate([
            'password' => $this->passwordRules(),
        ]);
        $attributes = $signup->registration_data;
        $verificationChannel = $signup->otp_channel;

        $registration = DB::transaction(function () use ($attributes, $validated, $verificationChannel, $request): ClientRegistration {
            $companyName = $attributes['company_name'] ?? $attributes['contact_name'];
            $registrationAttributes = $attributes;
            unset($registrationAttributes['identification_document'], $registrationAttributes['legal_acceptances']);
            $client = Client::query()->create([
                'name' => $companyName,
                'account_type' => $attributes['account_type'],
                'code' => $this->clientCode($companyName),
                'email' => $attributes['email'],
                'phone' => $attributes['phone'],
                'timezone' => $attributes['timezone'],
                'status' => ClientStatus::Pending,
                'status_changed_at' => now(),
                'credit_limit' => 0,
                'notes' => $attributes['message'] ?? null,
            ]);

            $registration = ClientRegistration::query()->create([
                ...$registrationAttributes,
                'status' => ClientRegistrationStatus::Pending,
            ]);
            $registration->forceFill(['client_id' => $client->id])->save();

            $user = User::query()->create([
                'name' => $attributes['contact_name'],
                'email' => $attributes['email'],
                'phone' => $attributes['phone'],
                'password' => $validated['password'],
                'client_id' => $client->id,
                'is_active' => true,
            ]);
            $user->forceFill($verificationChannel === 'sms'
                ? ['phone_verified_at' => now()]
                : ['email_verified_at' => now()])->save();
            $user->assignRole(Role::ClientAdmin->value);

            /*
            | The policies the applicant ticked before the one-time code was sent
            | are written now that the account exists. Each row names the version
            | that was live at that moment, so a later revision never rewrites
            | what this customer agreed to.
            */
            $this->legal->recordAcceptances(
                $user,
                array_values(array_filter(array_map(
                    fn (string $slug): ?LegalDocument => $this->legal->document($slug),
                    $attributes['legal_acceptances'] ?? [],
                ))),
                LegalAcceptanceContext::Registration,
                $request->ip(),
                $request->userAgent(),
            );

            $identification = $attributes['identification_document'];
            $client->documents()->create([
                'type' => ClientDocumentType::from($identification['type']),
                'title' => $identification['title'],
                'path' => $identification['path'],
                'storage_disk' => $identification['storage_disk'],
                'original_name' => $identification['original_name'],
                'mime_type' => $identification['mime_type'],
                'size_bytes' => $identification['size_bytes'],
                'status' => ClientDocumentStatus::Pending,
                'uploaded_by' => $user->id,
            ]);

            foreach (ClientService::cases() as $service) {
                ClientEntitlement::query()->create([
                    'client_id' => $client->id,
                    'service' => $service,
                    'is_enabled' => in_array($service->value, $attributes['services'], true),
                ]);
            }

            activity()->causedBy($user)->performedOn($registration)
                ->withProperties(['account_type' => $client->accountType()->value, 'company_name' => $attributes['company_name'], 'email' => $user->email])
                ->log('submitted verified client registration');

            return $registration;
        });

        $this->notifications->registrationSubmitted($registration);

        $request->session()->forget('client_signup_id');
        $signup->delete();

        return to_route('login')->with('toast', [
            'type' => 'success',
            'message' => 'Your account is ready. Sign in with your email or phone and password. Your organisation is awaiting review.',
        ]);
    }

    private function currentSignup(Request $request): ?ClientSignup
    {
        $id = $request->session()->get('client_signup_id');

        return is_int($id) ? ClientSignup::query()->find($id) : null;
    }

    private function deleteSignup(ClientSignup $signup): void
    {
        $identification = $signup->registration_data['identification_document'] ?? null;
        if (is_array($identification) && isset($identification['storage_disk'], $identification['path'])) {
            Storage::disk((string) $identification['storage_disk'])->delete((string) $identification['path']);
        }

        $signup->delete();
    }

    private function clientCode(string $companyName): string
    {
        $stem = Str::upper(Str::substr(Str::slug($companyName, ''), 0, 6)) ?: 'CLIENT';
        do {
            $code = $stem.'-'.Str::upper(Str::random(4));
        } while (Client::withTrashed()->where('code', $code)->exists());

        return $code;
    }
}
