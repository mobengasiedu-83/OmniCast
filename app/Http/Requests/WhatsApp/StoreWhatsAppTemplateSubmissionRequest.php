<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWhatsAppTemplateSubmissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->client_id !== null && $this->user()->can('whatsapp.connectors.view');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'connector_id' => ['required', 'integer', Rule::exists('whatsapp_client_connectors', 'connector_id')->where('client_id', $this->user()->client_id)->where('enabled', true)->where('approval_status', 'approved')],
            'name' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9_]+$/', Rule::unique('whatsapp_template_submissions')->where('client_id', $this->user()->client_id)->where('connector_id', $this->integer('connector_id'))->where('language', $this->string('language')->toString())->where(fn ($query) => $query->where('review_status', '!=', 'rejected'))],
            'language' => ['required', 'string', 'max:16', 'regex:/^[a-z]{2,3}(?:_[A-Z]{2})?$/'],
            'category' => ['required', Rule::in(['UTILITY', 'MARKETING', 'AUTHENTICATION'])],
            'body' => ['required', 'string', 'max:1024'],
        ];
    }
}
