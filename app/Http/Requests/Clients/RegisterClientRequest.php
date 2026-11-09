<?php

namespace App\Http\Requests\Clients;

use App\Enums\ClientAccountType;
use App\Enums\ClientRegistrationStatus;
use App\Enums\ClientService;
use App\Enums\LegalDocumentStatus;
use App\Models\LegalDocument;
use App\Services\CountryCatalog;
use App\Services\PhoneNumber;
use App\Services\PlatformConfiguration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Validates the public client application form.
 *
 * Only one open application per email address is accepted so a company cannot
 * flood the review queue while a decision is outstanding.
 */
class RegisterClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise the applicant's email address before validating it.
     */
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => mb_strtolower(trim($email))]);
        }

        $phone = $this->input('phone');
        if (is_string($phone)) {
            $this->merge(['phone' => app(PhoneNumber::class)->normalize($phone)]);
        }

        $country = $this->input('country');
        if (is_string($country)) {
            $this->merge(['country' => strtoupper(trim($country))]);
        }

        $accountType = $this->input('account_type');
        if (is_string($accountType)) {
            $this->merge(['account_type' => strtolower(trim($accountType))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $accountType = ClientAccountType::tryFrom((string) $this->input('account_type'));
        $maximumUploadKilobytes = max(1, (int) app(PlatformConfiguration::class)->resolve('storage', 'max_upload_kb', 5120));

        return [
            'account_type' => ['required', Rule::enum(ClientAccountType::class)],
            'company_name' => [Rule::when($accountType === ClientAccountType::Business, ['required', 'string', 'max:150'], ['nullable', 'string', 'max:150'])],
            'contact_name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
                Rule::unique('client_registrations', 'email')
                    ->where('status', ClientRegistrationStatus::Pending->value),
            ],
            'phone' => [
                'required', 'string', 'max:32', 'regex:/^\+[1-9]\d{7,14}$/',
                Rule::unique('users', 'phone'),
                Rule::unique('client_registrations', 'phone')
                    ->where('status', ClientRegistrationStatus::Pending->value),
            ],
            'country' => ['required', 'string', Rule::in(array_column(app(CountryCatalog::class)->all(), 'code'))],
            'timezone' => [
                'required', 'string', Rule::in(app(CountryCatalog::class)->timezones((string) $this->input('country'))),
            ],
            'otp_channel' => ['required', Rule::in(['email', 'sms'])],
            'identification_type' => [
                'required', 'string', Rule::in(array_map(
                    fn ($type): string => $type->value,
                    $accountType?->identificationTypes() ?? [],
                )),
            ],
            'identification_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.$maximumUploadKilobytes],
            'website' => ['nullable', 'string', 'url', 'max:255'],
            'services' => ['required', 'array', 'min:1'],
            'services.*' => ['required', 'string', Rule::enum(ClientService::class)],
            'expected_monthly_volume' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'message' => ['nullable', 'string', 'max:2000'],
            'marketing_opt_in' => ['nullable', 'boolean'],
            ...$this->policyRules(),
        ];
    }

    /**
     * One `accepted` rule per policy the platform collects consent for.
     *
     * The list comes from the published documents themselves, so adding a policy
     * to the registration screen is an admin change rather than a code change —
     * and a document that is unpublished or withdrawn stops being required
     * without anyone editing this request.
     *
     * @return array<string, mixed>
     */
    protected function policyRules(): array
    {
        $rules = [];

        foreach ($this->policies() as $document) {
            $rules['accept_'.$document->slug] = ['accepted'];
        }

        return $rules;
    }

    /**
     * The documents the applicant must acknowledge.
     *
     * @return Collection<int, LegalDocument>
     */
    protected function policies(): Collection
    {
        return LegalDocument::query()
            ->active()
            ->where('requires_acceptance_at_registration', true)
            ->whereHas('versions', fn (Builder $query) => $query->where('status', LegalDocumentStatus::Published->value))
            ->ordered()
            ->get();
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [
            'email.unique' => 'An application with this email address is already awaiting review.',
            'services.required' => 'Choose at least one service you would like to use.',
        ];

        foreach ($this->policies() as $document) {
            $messages['accept_'.$document->slug.'.accepted'] = "Please accept the {$document->title} to continue.";
        }

        return $messages;
    }

    /**
     * The policy slugs the applicant ticked.
     *
     * The answers travel with the signup so the acceptance can be written the
     * moment the account exists — the consent belongs to the moment it was given,
     * not to the moment the password was chosen.
     *
     * @return list<string>
     */
    public function acceptedLegalSlugs(): array
    {
        return $this->policies()
            ->filter(fn (LegalDocument $document): bool => $this->boolean('accept_'.$document->slug))
            ->map(fn (LegalDocument $document): string => $document->slug)
            ->values()
            ->all();
    }

    /**
     * The attributes used to open the application.
     *
     * @return array<string, mixed>
     */
    public function registrationAttributes(): array
    {
        $validated = $this->validated();

        return [
            'account_type' => $validated['account_type'],
            'company_name' => $validated['account_type'] === ClientAccountType::Business->value
                ? ($validated['company_name'] ?? null)
                : null,
            'contact_name' => $validated['contact_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'country' => $validated['country'] ?? null,
            'timezone' => $validated['timezone'],
            'website' => $validated['website'] ?? null,
            'services' => array_values($validated['services']),
            'expected_monthly_volume' => $validated['expected_monthly_volume'] ?? null,
            'message' => $validated['message'] ?? null,
            'status' => ClientRegistrationStatus::Pending,
            'ip_address' => $this->ip(),
        ];
    }
}
