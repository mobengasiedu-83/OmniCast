<?php

namespace App\Http\Requests\Billing;

use App\Enums\MobileMoneyProvider;
use App\Enums\Permission;
use App\Services\Billing\MobileMoneyNumber;
use App\Services\Billing\PackageCatalogue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A client funding the wallet by prompting its own mobile money wallet.
 */
class MobileMoneyCheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::TopUpWallet->value) ?? false;
    }

    /**
     * Read the wallet number into the local shape the gateway charges.
     */
    protected function prepareForValidation(): void
    {
        $number = MobileMoneyNumber::parse($this->string('phone')->toString());

        if ($number !== null) {
            $this->merge(['phone' => $number->local]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'package' => ['required', 'string', Rule::in($this->packages()->keys())],
            'phone' => ['required', 'string', 'regex:/^0\d{9}$/'],
            'provider' => ['nullable', 'string', Rule::enum(MobileMoneyProvider::class)],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
        ];
    }

    /**
     * The messages shown when the package or the number cannot be used.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'package.required' => 'Choose one of the wallet top up packages.',
            'package.in' => 'Choose one of the wallet top up packages.',
            'phone.required' => 'Enter the mobile money number to charge.',
            'phone.regex' => 'Enter a Ghanaian mobile money number, for example 024 421 5189.',
        ];
    }

    /**
     * The package key that was chosen.
     */
    public function packageKey(): string
    {
        return $this->string('package')->toString();
    }

    /**
     * The number the charge is sent to, already normalised by the rules.
     */
    public function number(): MobileMoneyNumber
    {
        $number = MobileMoneyNumber::parse($this->string('phone')->toString());

        if ($number === null) {
            throw ValidationException::withMessages([
                'phone' => 'Enter a Ghanaian mobile money number, for example 024 421 5189.',
            ]);
        }

        return $number;
    }

    /**
     * The network to charge: the one chosen, the one the prefix belongs to, or
     * MTN, which issues most of the wallets in Ghana.
     */
    public function provider(): MobileMoneyProvider
    {
        return MobileMoneyProvider::tryFrom($this->string('provider')->toString())
            ?? $this->number()->provider
            ?? MobileMoneyProvider::Mtn;
    }

    /**
     * The catalogue the submitted key is checked against.
     */
    protected function packages(): PackageCatalogue
    {
        return app(PackageCatalogue::class);
    }
}
