<?php

namespace App\Http\Requests\Privacy;

use App\Enums\PrivacyRequestStatus;
use App\Models\PrivacyRequest as PrivacyRequestModel;
use Illuminate\Foundation\Http\FormRequest;

class ReviewPrivacyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $privacyRequest = $this->route('privacyRequest');

        return ($this->user()?->can('legal.manage') ?? false)
            && $privacyRequest instanceof PrivacyRequestModel
            && $privacyRequest->status === PrivacyRequestStatus::Requested
            && (int) $privacyRequest->requested_by !== (int) $this->user()?->getAuthIdentifier();
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:approve,reject'],
        ];
    }
}
