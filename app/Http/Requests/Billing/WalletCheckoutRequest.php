<?php

namespace App\Http\Requests\Billing;

use App\Enums\Permission;
use App\Services\Billing\CreditEstimator;
use App\Services\Billing\PackageCatalogue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A client funding the wallet: a catalogue package, or an amount they chose on
 * the slider.
 */
class WalletCheckoutRequest extends FormRequest
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
     * One of the two is required: the package key is checked against the
     * catalogue, and a client-chosen amount against the top up limits. When the
     * platform switches dynamic pricing off, only a package may be bought and a
     * submitted amount is refused.
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
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
        ];
    }

    /**
     * The messages shown when the package key or the amount cannot be used.
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
