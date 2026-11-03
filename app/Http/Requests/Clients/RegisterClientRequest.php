<?php

namespace App\Http\Requests\Clients;

use App\Enums\ClientRegistrationStatus;
use App\Enums\ClientService;
use Illuminate\Foundation\Http\FormRequest;
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
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:150'],
            'contact_name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('client_registrations', 'email')
                    ->where('status', ClientRegistrationStatus::Pending->value),
            ],
            'phone' => ['required', 'string', 'max:32', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'country' => ['nullable', 'string', 'max:64'],
            'timezone' => ['required', 'string', 'timezone'],
            'website' => ['nullable', 'string', 'url', 'max:255'],
            'services' => ['required', 'array', 'min:1'],
            'services.*' => ['required', 'string', Rule::enum(ClientService::class)],
            'expected_monthly_volume' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'An application with this email address is already awaiting review.',
            'services.required' => 'Choose at least one service you would like to use.',
        ];
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
            'company_name' => $validated['company_name'],
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
