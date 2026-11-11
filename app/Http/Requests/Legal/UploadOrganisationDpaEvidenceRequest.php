<?php

namespace App\Http\Requests\Legal;

use Illuminate\Foundation\Http\FormRequest;

class UploadOrganisationDpaEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('legal.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'document' => ['required', 'file', 'mimes:pdf', 'extensions:pdf', 'max:20480'],
        ];
    }
}
