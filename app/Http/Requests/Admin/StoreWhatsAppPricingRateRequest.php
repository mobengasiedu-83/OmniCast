<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWhatsAppPricingRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('rates.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'market' => ['required', 'string', 'size:2', 'alpha', 'uppercase'],
            'category' => ['required', Rule::in(['marketing', 'utility', 'authentication'])],
            'volume_from' => ['required', 'integer', 'min:0'],
            'volume_to' => ['nullable', 'integer', 'gte:volume_from'],
            'meta_rate' => ['required', 'numeric', 'min:0'],
            'provider_fee' => ['required', 'numeric', 'min:0'],
            'direct_cost' => ['required', 'numeric', 'min:0'],
            'fx_rate' => ['required', 'numeric', 'gt:0'],
            'markup_percent' => ['required', 'numeric', 'min:0', 'max:1000'],
            'minimum_price' => ['required', 'numeric', 'min:0'],
            'source' => ['required', 'string', 'max:255'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }
}
