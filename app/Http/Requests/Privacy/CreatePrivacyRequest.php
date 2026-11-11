<?php

namespace App\Http\Requests\Privacy;

use App\Enums\PrivacyRequestAction;
use App\Enums\PrivacySubjectType;
use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CreatePrivacyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('legal.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')],
            'subject_type' => ['required', Rule::enum(PrivacySubjectType::class)],
            'action' => ['required', Rule::enum(PrivacyRequestAction::class)],
            'contact_id' => [
                'nullable',
                'integer',
                Rule::requiredIf($this->input('subject_type') === PrivacySubjectType::Contact->value),
                Rule::prohibitedIf($this->input('subject_type') === PrivacySubjectType::Account->value),
                Rule::exists('contacts', 'id'),
            ],
        ];
    }

    /**
     * Ensure a contact request cannot cross client boundaries.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['client_id', 'subject_type', 'contact_id'])
                    || $this->input('subject_type') !== PrivacySubjectType::Contact->value) {
                    return;
                }

                $belongsToClient = Contact::withTrashed()
                    ->whereKey($this->integer('contact_id'))
                    ->where('client_id', $this->integer('client_id'))
                    ->exists();

                if (! $belongsToClient) {
                    $validator->errors()->add('contact_id', 'Choose a contact that belongs to the selected client account.');
                }
            },
        ];
    }
}
