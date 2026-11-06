<?php

namespace App\Http\Requests\Billing;

use App\Enums\MobileMoneyProvider;
use App\Enums\Permission;
use App\Services\Billing\CreditEstimator;
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
        $estimator = $this->estimator();
        $limits = $estimator->limits();
        $dynamic = $estimator->dynamicPricingEnabled();

        return [
            'package' => $dynamic
                ? ['nullable', 'string', 'required_without:amount', Rule::in($this->packages()->keys())]
                : ['required', 'string', Rule::in($this->packages()->keys())],
            'amount' => $dynamic
                ? [
                    'nullable',
                    'numeric',
                    'required_without:package',
                    'min:'.$limits['min'],
                    'max:'.$limits['max'],
                ]
                : ['prohibited'],
            'phone' => ['required', 'string', 'regex:/^0\d{9}$/'],
            'provider' => ['nullable', 'string', Rule::enum(MobileMoneyProvider::class)],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
        ];
    }

    /**
     * The messages shown when the package, the amount or the number cannot be
     * used.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'package.required' => 'Choose one of the wallet top up packages.',
            'package.required_without' => 'Choose one of the wallet top up packages, or enter the amount you want to pay.',
            'package.in' => 'Choose one of the wallet top up packages.',
            'amount.required' => 'Enter the amount you want to pay, or choose a package.',
            'amount.required_without' => 'Enter the amount you want to pay, or choose a package.',
            'amount.numeric' => 'Enter the amount as a number, for example 150.00.',
            'amount.min' => 'The smallest top up is :min.',
            'amount.max' => 'The largest top up is :max.',
            'amount.prohibited' => 'Choosing your own amount is switched off. Choose one of the wallet top up packages.',
            'phone.required' => 'Enter the mobile money number to charge.',
            'phone.regex' => 'Enter a Ghanaian mobile money number, for example 024 421 5189.',
        ];
    }

    /**
     * The package key that was chosen, when a package was chosen.
     */
    public function packageKey(): ?string
    {
        $package = trim($this->string('package')->toString());

        return $package === '' ? null : $package;
    }

    /**
     * The amount the client chose to pay, when they named one.
     */
    public function amount(): ?float
    {
        $amount = $this->input('amount');

        return is_numeric($amount) ? (float) $amount : null;
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

    /**
     * The published price list, for the top up limits.
     */
    protected function estimator(): CreditEstimator
    {
        return app(CreditEstimator::class);
    }
}
