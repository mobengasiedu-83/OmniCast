<?php

namespace App\Http\Requests\Clients;

use App\Models\ClientRegistration;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the rejection of a client application.
 */
class RejectClientRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $registration = $this->route('registration');

        return $registration instanceof ClientRegistration
            && ($this->user()?->can('reject', $registration) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
