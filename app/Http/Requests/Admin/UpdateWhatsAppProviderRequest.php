<?php

namespace App\Http\Requests\Admin;

use App\Models\WhatsAppProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWhatsAppProviderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $provider = $this->route('provider');

        return $provider instanceof WhatsAppProvider
            && ($this->user()?->can('update', $provider) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('whatsapp_providers', 'name')->ignore($this->route('provider'))],
            'driver' => ['required', Rule::in(['pro_plus'])],
            'is_enabled' => ['required', 'boolean'],
            'connect_timeout' => ['required', 'integer', 'between:1,60'],
            'request_timeout' => ['required', 'integer', 'between:1,120'],
        ];
    }
}
