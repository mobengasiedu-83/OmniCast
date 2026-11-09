<?php

namespace App\Http\Requests\Legal;

use App\Enums\OrganisationDpaStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the platform's record of an organisation's DPA.
 *
 * The signature is recorded here rather than uploaded: the countersigned copy is
 * exchanged by email as part of onboarding, and the platform team enters who
 * signed it and when so every member of the account can see the same status.
 */
class OrganisationDpaRequest extends FormRequest
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
            'status' => ['required', Rule::enum(OrganisationDpaStatus::class)],
            'signatory_name' => ['nullable', 'string', 'max:120'],
            'signatory_email' => ['nullable', 'string', 'email', 'max:160'],
            'signed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Choose where the agreement stands.',
        ];
    }

    /**
     * The status the platform team selected.
     */
    public function status(): OrganisationDpaStatus
    {
        return OrganisationDpaStatus::from((string) $this->validated('status'));
    }
}
