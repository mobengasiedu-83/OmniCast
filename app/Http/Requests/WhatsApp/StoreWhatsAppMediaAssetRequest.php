<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreWhatsAppMediaAssetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return ($this->user()?->client_id !== null && $this->user()->can('whatsapp.connectors.view'))
            || ($this->user()?->isPlatformUser() && $this->user()->can('whatsapp.connectors.manage'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.(int) config('whatsapp.media.max_kilobytes', 16384), 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,application/pdf'],
        ];
    }
}
