<?php

namespace App\Http\Requests\Template;

/**
 * The platform catalogue keeps its templates on the platform itself, so an
 * administrator never files a system template under a client tenant.
 */
class SystemTemplateRequest extends TemplateRequest
{
    /**
     * Only a platform user may add to the catalogue.
     */
    public function authorize(): bool
    {
        return ($this->user()?->isPlatformUser() ?? false) && parent::authorize();
    }

    /**
     * Every catalogue template belongs to the platform, never to a tenant.
     */
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge(['client_id' => null]);
    }

    /**
     * The tenant scope rule does not apply to the catalogue.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'client_id' => ['nullable'],
        ];
    }

    /**
     * Pin the row to the platform catalogue.
     *
     * @return array<string, mixed>
     */
    public function templateAttributes(): array
    {
        return [
            ...parent::templateAttributes(),
            'client_id' => null,
        ];
    }
}
