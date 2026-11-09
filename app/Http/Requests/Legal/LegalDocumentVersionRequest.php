<?php

namespace App\Http\Requests\Legal;

use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a new version of a policy, or an edit to one that is still unused.
 *
 * Versions are numbered by hand rather than guessed, because the number is
 * quoted in the acceptance a customer gave. The body is markdown, which the
 * public page renders itself; raw HTML is stripped before it reaches a browser.
 */
class LegalDocumentVersionRequest extends FormRequest
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
        $version = $this->route('version');

        return [
            'version' => [
                'required', 'string', 'max:20', 'regex:/^\d+(\.\d+)*$/',
                Rule::unique('legal_document_versions', 'version')
                    ->where('legal_document_id', $this->documentId())
                    ->ignore($version instanceof LegalDocumentVersion ? $version->id : null),
            ],
            'body' => ['required', 'string', 'min:50'],
            'change_summary' => ['nullable', 'string', 'max:1000'],
            'effective_on' => ['nullable', 'date'],
            'requires_reacceptance' => ['boolean'],
            'publish' => ['boolean'],
        ];
    }

    /**
     * The document a version belongs to, whether the route names the document
     * (writing) or the version being edited.
     */
    protected function documentId(): ?int
    {
        $document = $this->route('document');
        $version = $this->route('version');

        return match (true) {
            $document instanceof LegalDocument => $document->id,
            $version instanceof LegalDocumentVersion => $version->legal_document_id,
            default => null,
        };
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'version.regex' => 'Number the version like 1.0, 1.1 or 2.0.',
            'version.unique' => 'That version already exists for this document.',
            'body.min' => 'Paste the full policy text: a version has to be readable on its own.',
        ];
    }

    /**
     * The attributes written to the version.
     *
     * @return array<string, mixed>
     */
    public function versionAttributes(): array
    {
        $validated = $this->validated();

        return [
            'version' => $validated['version'],
            'body' => $validated['body'],
            'change_summary' => $validated['change_summary'] ?? null,
            'effective_on' => $validated['effective_on'] ?? null,
            'requires_reacceptance' => $this->boolean('requires_reacceptance', true),
        ];
    }

    /**
     * Whether the administrator wants the version live straight away.
     */
    public function publishesImmediately(): bool
    {
        return $this->boolean('publish');
    }
}
