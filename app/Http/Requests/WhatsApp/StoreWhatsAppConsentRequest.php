<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWhatsAppConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->client_id !== null && $this->user()->can('whatsapp.connectors.view');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'contact_id' => ['required', 'integer', Rule::exists('contacts', 'id')->where('client_id', $this->user()->client_id)],
            'purpose' => ['required', Rule::in(['marketing', 'utility', 'authentication'])],
            'source' => ['required', 'string', 'max:120'],
            'wording' => ['required', 'string', 'max:2000'],
            'granted_at' => ['required', 'date', 'before_or_equal:now'],
        ];
    }
}
