<?php

namespace App\Http\Requests\Wallet;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class TopUpWalletRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:1', 'max:100000'],
            'reference' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:191'],
        ];
    }
}
