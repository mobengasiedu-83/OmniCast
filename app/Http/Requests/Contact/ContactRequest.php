<?php

namespace App\Http\Requests\Contact;

use App\Enums\Permission;
use App\Models\Contact;
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
        $userClientId = $this->user()?->client_id;

        $this->merge([
            'client_id' => $userClientId ?? $this->input('client_id'),
            'phone' => is_string($phone) && $phone !== ''
                ? app(PhoneNumber::class)->normalize($phone)
                : $phone,
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : $this->input('email'),
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
        $routeContact = $this->route('contact');
        $contactId = $routeContact instanceof Contact ? $routeContact->id : null;

        return [
            'client_id' => [Rule::requiredIf($this->user()?->isPlatformUser() ?? false), 'integer', Rule::exists('clients', 'id')],
            'first_name' => ['nullable', 'string', 'max:100'],
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
            'dnd' => ['boolean'],
            'contact_list_ids' => ['array'],
            'contact_list_ids.*' => ['integer', Rule::exists('contact_lists', 'id')->where('client_id', $clientId)],
            'tag_ids' => ['array'],
            'tag_ids.*' => ['integer', Rule::exists('tags', 'id')->where('client_id', $clientId)],
            'custom_fields' => ['nullable', 'array'],
            'custom_fields.*' => ['nullable', 'string', 'max:2000'],
            'custom_fields_json' => ['nullable', 'json', 'max:10000'],
        ];
    }

    /**
     * The contact attributes that may be persisted.
     *
     * @return array<string, mixed>
     */
    public function contactAttributes(): array
    {
        $attributes = $this->safe()->except(['contact_list_ids', 'tag_ids', 'custom_fields_json']);

        if ($this->filled('custom_fields_json')) {
            $decoded = json_decode($this->string('custom_fields_json')->toString(), true);
            $attributes['custom_fields'] = is_array($decoded)
                ? collect($decoded)->filter(fn (mixed $value): bool => is_string($value) || is_numeric($value) || is_bool($value))
                    ->map(fn (mixed $value): string => mb_substr((string) $value, 0, 2000))
                    ->all()
                : [];
        }

        foreach (['language', 'timezone', 'client_id'] as $key) {
            if (($attributes[$key] ?? null) === null) {
                unset($attributes[$key]);
            }
        }

        return $attributes;
    }
}
