<?php

namespace App\Http\Requests\Notification;

use App\Enums\NotificationCategory;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Saves the channel matrix on `settings/notifications`.
 *
 * Only the known categories are accepted, so a stale form cannot write a row
 * for a category the notification centre does not have.
 */
class NotificationPreferenceRequest extends FormRequest
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
            'preferences' => ['required', 'array:'.implode(',', NotificationCategory::values())],
            'preferences.*' => ['array'],
            'preferences.*.in_app' => ['boolean'],
            'preferences.*.email' => ['boolean'],
        ];
    }

    /**
     * The submitted matrix, keyed by category.
     *
     * @return array<string, array{in_app?: mixed, email?: mixed}>
     */
    public function preferences(): array
    {
        /** @var array<string, array{in_app?: mixed, email?: mixed}> $preferences */
        $preferences = $this->input('preferences', []);

        return $preferences;
    }
}
