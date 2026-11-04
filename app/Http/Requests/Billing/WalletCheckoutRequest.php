<?php

namespace App\Http\Requests\Billing;

use App\Enums\Permission;
use App\Services\Billing\PackageCatalogue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A client picking the package they want to fund the wallet with.
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'package' => ['required', 'string', Rule::in($this->packages()->keys())],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
        ];
    }

    /**
     * The messages shown when the package key is not in the catalogue.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'package.required' => 'Choose one of the wallet top up packages.',
            'package.in' => 'Choose one of the wallet top up packages.',
        ];
    }

    /**
     * The package key that was chosen.
     */
    public function packageKey(): string
    {
        return (string) $this->string('package');
    }

    /**
     * The catalogue the submitted key is checked against.
     */
    protected function packages(): PackageCatalogue
    {
        return app(PackageCatalogue::class);
    }
}
