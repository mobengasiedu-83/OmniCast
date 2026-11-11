<?php

namespace App\Http\Requests\Privacy;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePrivacyRetentionPolicy extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('legal.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'days' => ['required', 'integer', 'between:1,36500'],
            'legal_approval' => ['required', 'accepted'],
        ];
    }
}
