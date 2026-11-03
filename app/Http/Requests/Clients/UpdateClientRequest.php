<?php

namespace App\Http\Requests\Clients;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates edits to a tenant's profile.
 *
 * The tenant code is generated on approval and never edited here: it is the
 * stable identifier used in invoices and support conversations.
 */
class UpdateClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $client = $this->route('client');

        return $client instanceof Client && ($this->user()?->can('update', $client) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'timezone' => ['required', 'string', 'timezone'],
            'credit_limit' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * The attributes that may be updated on the tenant.
     *
     * @return array<string, mixed>
     */
    public function clientAttributes(): array
    {
        return $this->safe()->only(['name', 'email', 'phone', 'timezone', 'credit_limit', 'notes']);
    }
}
