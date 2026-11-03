<?php

namespace App\Http\Requests\Clients;

use App\Enums\ClientAccountType;
use App\Enums\ClientDocumentType;
use App\Models\Client;
use App\Services\PlatformConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an uploaded compliance document.
 */
class StoreClientDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $client = $this->route('client');

        if ($client instanceof Client) {
            return $this->user()?->can('update', $client) ?? false;
        }

        $tenant = $this->user()?->client;

        return $tenant instanceof Client && (int) $tenant->id === (int) $this->user()?->client_id;
    }

    /**
     * Default the document title to the type's label.
     */
    protected function prepareForValidation(): void
    {
        if (blank($this->input('title'))) {
            $type = ClientDocumentType::tryFrom((string) $this->input('type'));

            if ($type !== null) {
                $this->merge(['title' => $type->label()]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $client = $this->route('client');
        if (! $client instanceof Client) {
            $client = $this->user()?->client;
        }
        $accountType = $client?->accountType() ?? ClientAccountType::Business;
        $maximumUploadKilobytes = max(1, (int) app(PlatformConfiguration::class)->resolve(
            'storage',
            'max_upload_kb',
            5120,
            $client instanceof Client ? $client : null,
        ));

        return [
            'type' => ['required', 'string', Rule::in(array_map(
                fn (ClientDocumentType $type): string => $type->value,
                $accountType->identificationTypes(),
            ))],
            'title' => ['required', 'string', 'max:150'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.$maximumUploadKilobytes],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ];
    }
}
