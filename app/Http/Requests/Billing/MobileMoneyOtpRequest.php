<?php

namespace App\Http\Requests\Billing;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The one time password a mobile money wallet sent to the payer.
 */
class MobileMoneyOtpRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::TopUpWallet->value) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'otp' => ['required', 'string', 'digits:6'],
        ];
    }

    /**
     * The messages shown when the code is not six digits.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'otp.required' => 'Enter the code the wallet sent to the phone.',
            'otp.digits' => 'The mobile money code is six digits.',
        ];
    }

    /**
     * The code that was typed.
     */
    public function otp(): string
    {
        return $this->string('otp')->toString();
    }
}
