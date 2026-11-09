<?php

namespace App\Http\Requests\Legal;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates where acceptance of a policy is collected.
 *
 * The three switches are the only part of a document that may be changed
 * without writing a new version: they describe the platform's behaviour, not
 * the wording a customer agreed to.
 */
class LegalRequirementsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('legal.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'requires_acceptance_at_registration' => ['required', 'boolean'],
            'requires_acceptance_on_campaign_submission' => ['required', 'boolean'],
            'requires_acceptance_on_wallet_top_up' => ['required', 'boolean'],
        ];
    }

    /**
     * The acceptance switches, as they are stored on the document.
     *
     * @return array<string, bool>
     */
    public function requirementAttributes(): array
    {
        $validated = $this->validated();

        return [
            'requires_acceptance_at_registration' => (bool) $validated['requires_acceptance_at_registration'],
            'requires_acceptance_on_campaign_submission' => (bool) $validated['requires_acceptance_on_campaign_submission'],
            'requires_acceptance_on_wallet_top_up' => (bool) $validated['requires_acceptance_on_wallet_top_up'],
        ];
    }
}
