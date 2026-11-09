<?php

namespace App\Http\Requests\Legal;

use App\Models\LegalDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a policy document's identity and acceptance rules.
 *
 * The slug is the public URL segment, so it is validated as a stable, lowercase
 * dash-separated handle: changing it later would break links customers and
 * regulators have already saved.
 */
class LegalDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('legal.manage') ?? false;
    }

    /**
     * Normalise the handle before validating it.
     */
    protected function prepareForValidation(): void
    {
        $slug = $this->input('slug');

        if (is_string($slug)) {
            $this->merge(['slug' => str($slug)->slug()->value()]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $document = $this->route('document');

        return [
            'slug' => [
                'required', 'string', 'max:80', 'alpha_dash',
                Rule::unique('legal_documents', 'slug')
                    ->ignore($document instanceof LegalDocument ? $document->id : null),
            ],
            'title' => ['required', 'string', 'max:160'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
            'requires_acceptance_at_registration' => ['boolean'],
            'requires_acceptance_on_campaign_submission' => ['boolean'],
            'requires_acceptance_on_wallet_top_up' => ['boolean'],
        ];
    }

    /**
     * The attributes written to the document.
     *
     * @return array<string, mixed>
     */
    public function documentAttributes(): array
    {
        $validated = $this->validated();

        return [
            'slug' => $validated['slug'],
            'title' => $validated['title'],
            'summary' => $validated['summary'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => $this->boolean('is_active', true),
            'requires_acceptance_at_registration' => $this->boolean('requires_acceptance_at_registration'),
            'requires_acceptance_on_campaign_submission' => $this->boolean('requires_acceptance_on_campaign_submission'),
            'requires_acceptance_on_wallet_top_up' => $this->boolean('requires_acceptance_on_wallet_top_up'),
        ];
    }
}
