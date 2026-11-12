<?php

namespace App\Http\Requests\WhatsApp;

use App\Enums\WhatsAppConnectorStatus;
use App\Models\WhatsAppClientConnector;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreWhatsAppConnectorAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', WhatsAppClientConnector::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'connector_id' => [
                'required', 'integer',
                Rule::exists('whatsapp_connectors', 'id')->where('status', WhatsAppConnectorStatus::Available->value),
            ],
        ];
    }

    protected function passedValidation(): void
    {
        if (WhatsAppClientConnector::query()->where('client_id', $this->user()->client_id)->where('connector_id', $this->integer('connector_id'))->exists()) {
            throw ValidationException::withMessages(['connector_id' => 'A request for this sender already exists.']);
        }
    }
}
