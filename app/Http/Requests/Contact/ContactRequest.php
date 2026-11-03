<?php

namespace App\Http\Requests\Contact;

use App\Enums\ContactConsentStatus;
use App\Enums\Permission;
use App\Services\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ManageContacts->value) ?? false;
    }

    /**
     * Normalise the phone number and scope the payload to the tenant.
     */
    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        $this->merge([
            'client_id' => $this->user()?->client_id ?? $this->input('client_id'),
            'phone' => is_string($phone) && $phone !== ''
                ? app(PhoneNumber::class)->normalize($phone)
                : $phone,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $clientId = $this->input('client_id');
        $contactId = $this->route('contact')?->id;

        return [
            'client_id' => [Rule::requiredIf($this->user()?->isPlatformUser() ?? false), 'integer', Rule::exists('clients', 'id')],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => [
                'required',
                'string',
                'regex:/^\+[1-9]\d{7,14}$/',
                Rule::unique('contacts', 'phone')
                    ->where('client_id', $clientId)
                    ->whereNull('deleted_at')
                    ->ignore($contactId),
            ],
            'alternative_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:191'],
            'company' => ['nullable', 'string', 'max:191'],
            'account_number' => ['nullable', 'string', 'max:60'],
            'customer_id' => ['nullable', 'string', 'max:60'],
            'location' => ['nullable', 'string', 'max:191'],
            'language' => ['nullable', 'string', 'max:12'],
            'timezone' => ['nullable', 'string', 'timezone:all'],
            'consent_status' => ['nullable', Rule::enum(ContactConsentStatus::class)],
            'is_do_not_contact' => ['boolean'],
            'contact_list_ids' => ['array'],
            'contact_list_ids.*' => ['integer', Rule::exists('contact_lists', 'id')->where('client_id', $clientId)],
        ];
    }

    /**
     * The contact attributes that may be persisted.
     *
     * @return array<string, mixed>
     */
    public function contactAttributes(): array
    {
        $attributes = $this->safe()->except(['contact_list_ids']);

        foreach (['consent_status', 'language', 'timezone', 'client_id'] as $key) {
            if (($attributes[$key] ?? null) === null) {
                unset($attributes[$key]);
            }
        }

        return $attributes;
    }
}
