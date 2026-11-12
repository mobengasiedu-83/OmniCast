<?php

namespace App\Http\Requests\WhatsApp;

use App\Enums\WhatsAppTemplateStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWhatsAppCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->client_id !== null && $this->user()->can('whatsapp.connectors.view');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $clientId = $this->user()->client_id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'connector_id' => ['required', 'integer', Rule::exists('whatsapp_client_connectors', 'connector_id')->where('client_id', $clientId)->where('enabled', true)->where('approval_status', 'approved')],
            'template_id' => ['required', 'integer', Rule::exists('whatsapp_templates', 'id')->where('status', WhatsAppTemplateStatus::Approved->value)],
            'contact_list_id' => ['nullable', 'integer', Rule::exists('contact_lists', 'id')->where('client_id', $clientId)],
            'contact_segment_id' => ['nullable', 'integer', Rule::exists('contact_segments', 'id')->where('client_id', $clientId)],
            'variable_mapping' => ['nullable', 'array'],
            'variable_mapping.*' => ['nullable', 'string', 'max:255'],
            'media_asset_id' => ['nullable', 'integer', Rule::exists('whatsapp_media_assets', 'id')->where('client_id', $clientId)],
            'schedule_type' => ['required', Rule::in(['now', 'scheduled'])],
            'scheduled_at' => ['nullable', 'required_if:schedule_type,scheduled', 'date', 'after:now'],
            'timezone' => ['required', 'timezone:all'],
        ];
    }
}
