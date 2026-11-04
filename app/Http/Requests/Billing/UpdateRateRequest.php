<?php

namespace App\Http\Requests\Billing;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A new unit price on an existing rate.
 */
class UpdateRateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ManageRates->value) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'unit_price' => ['required', 'numeric', 'min:0', 'max:9999'],
            'supplier_cost' => ['required', 'numeric', 'min:0', 'max:9999'],
        ];
    }

    /**
     * The messages shown when a price is rejected.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit_price.required' => 'Enter the price the client is billed.',
            'supplier_cost.required' => 'Enter what the route costs the platform.',
            'unit_price.min' => 'A rate cannot be negative.',
            'supplier_cost.min' => 'A supplier cost cannot be negative.',
        ];
    }
}
