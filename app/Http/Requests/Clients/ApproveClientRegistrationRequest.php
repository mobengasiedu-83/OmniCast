<?php

namespace App\Http\Requests\Clients;

use App\Models\ClientRegistration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the approval of a client application.
 */
class ApproveClientRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $registration = $this->route('registration');

        return $registration instanceof ClientRegistration
            && ($this->user()?->can('approve', $registration) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:32', 'alpha_dash', Rule::unique('clients', 'code')],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
        ];
    }

    /**
     * The tenant attribute overrides supplied by the reviewer.
     *
     * @return array{name?: string|null, code?: string|null, credit_limit?: float|int|string|null}
     */
    public function overrides(): array
    {
        /** @var array{name?: string|null, code?: string|null, credit_limit?: float|int|string|null} $overrides */
        $overrides = $this->safe()->only(['name', 'code', 'credit_limit']);

        return array_filter($overrides, fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
