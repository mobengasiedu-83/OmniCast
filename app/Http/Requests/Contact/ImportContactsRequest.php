<?php

namespace App\Http\Requests\Contact;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportContactsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ImportContacts->value) ?? false;
    }

    /**
     * Scope the payload to the tenant of the signed-in user.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'client_id' => $this->user()?->client_id ?? $this->input('client_id'),
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

        return [
            'client_id' => [Rule::requiredIf($this->user()?->isPlatformUser() ?? false), 'integer', Rule::exists('clients', 'id')],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
            'contact_list_id' => ['nullable', 'integer', Rule::exists('contact_lists', 'id')->where('client_id', $clientId)],
        ];
    }
}
