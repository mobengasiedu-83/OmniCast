<?php

namespace App\Http\Requests\Template;

/**
 * The platform catalogue is not owned by a tenant, so the client scope the
 * library request insists on is replaced with a null owner.
 */
class CatalogueTemplateRequest extends TemplateRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'client_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * Pin the owner to the platform so a catalogue entry is never filed under a
     * client by accident.
     */
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge(['client_id' => null]);
    }
}
