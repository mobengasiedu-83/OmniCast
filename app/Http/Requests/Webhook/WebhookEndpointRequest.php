<?php

namespace App\Http\Requests\Webhook;

use App\Enums\WebhookEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creates or edits a webhook subscription.
 *
 * The URL is required to be HTTPS: every delivery carries a signature over the
 * body, and a plain HTTP endpoint would let anyone on the path replay it.
 */
class WebhookEndpointRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'url' => ['required', 'url:https', 'max:2048'],
            'description' => ['nullable', 'string', 'max:255'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(WebhookEvent::values())],
            'is_active' => ['boolean'],
            'client_id' => [
                Rule::requiredIf(fn (): bool => $this->user()?->isPlatformUser() ?? false),
                'nullable',
                'integer',
                Rule::exists('clients', 'id'),
            ],
        ];
    }

    /**
     * The message shown when the URL is not HTTPS.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'url.url' => 'The endpoint must be an https URL so the signed payload cannot be intercepted.',
        ];
    }

    /**
     * The event values, de-duplicated and ordered the way the enum lists them.
     *
     * @return list<string>
     */
    public function events(): array
    {
        /** @var list<string> $events */
        $events = array_values(array_filter(
            (array) $this->input('events', []),
            fn (mixed $event): bool => is_string($event) && in_array($event, WebhookEvent::values(), true),
        ));

        return array_values(array_unique($events));
    }
}
