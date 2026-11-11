<?php

namespace App\Http\Requests\Privacy;

use App\Enums\PrivacyRequestAction;
use App\Enums\PrivacyRequestStatus;
use App\Models\PrivacyRequest as PrivacyRequestModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ErasePrivacyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $privacyRequest = $this->route('privacyRequest');

        return ($this->user()?->can('legal.manage') ?? false)
            && $privacyRequest instanceof PrivacyRequestModel
            && $privacyRequest->action === PrivacyRequestAction::Erasure
            && $privacyRequest->status === PrivacyRequestStatus::Approved
            && (int) $privacyRequest->requested_by !== (int) $this->user()?->getAuthIdentifier();
    }

    public function rules(): array
    {
        return [
            'confirmation' => [
                'required',
                'string',
                Rule::in([(string) $this->route('privacyRequest')?->reference]),
            ],
        ];
    }
}
