<?php

namespace App\Http\Requests\SenderId;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SenderIdRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ManageSenderIds->value) ?? false;
    }

    /**
     * Scope the payload to the tenant of the signed-in user.
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        $this->merge([
            'client_id' => $this->user()?->client_id ?? $this->input('client_id'),
            'name' => is_string($name) ? strtoupper(trim($name)) : $name,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_id' => [Rule::requiredIf($this->user()?->isPlatformUser() ?? false), 'integer', Rule::exists('clients', 'id')],
            'name' => [
                'required',
                'string',
                'min:3',
                'max:11',
                'regex:/^[A-Z0-9]+$/',
                Rule::unique('sender_ids', 'name')
                    ->where('client_id', $this->input('client_id'))
                    ->whereNull('deleted_at'),
            ],
            'country' => ['required', 'string', 'size:2'],
            'purpose' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }
}
