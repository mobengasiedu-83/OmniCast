<?php

namespace App\Http\Requests\Clients;

use App\Enums\ClientDocumentType;
use App\Models\Client;
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

        return $client instanceof Client && ($this->user()?->can('update', $client) ?? false);
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
        return [
            'type' => ['required', 'string', Rule::enum(ClientDocumentType::class)],
            'title' => ['required', 'string', 'max:150'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ];
    }
}
