<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class CompleteMetaWhatsAppSignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->client_id !== null
            && ($this->user()?->can('whatsapp.connectors.view') ?? false);
    }

    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:4096'],
            'waba_id' => ['required', 'string', 'regex:/^\d{5,32}$/'],
            'phone_number_id' => ['required', 'string', 'regex:/^\d{5,32}$/'],
            'business_id' => ['nullable', 'string', 'regex:/^\d{5,32}$/'],
        ];
    }
}
