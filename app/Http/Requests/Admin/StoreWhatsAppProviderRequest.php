<?php

namespace App\Http\Requests\Admin;

use App\Models\WhatsAppProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWhatsAppProviderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', WhatsAppProvider::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', 'unique:whatsapp_providers,name'],
            'driver' => ['required', Rule::in(['pro_plus'])],
            'is_enabled' => ['required', 'boolean'],
            'connect_timeout' => ['required', 'integer', 'between:1,60'],
            'request_timeout' => ['required', 'integer', 'between:1,120'],
        ];
    }
}
