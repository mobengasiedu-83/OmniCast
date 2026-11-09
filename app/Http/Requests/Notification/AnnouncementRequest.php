<?php

namespace App\Http\Requests\Notification;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementSeverity;
use App\Enums\NotificationCategory;
use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Composes a platform announcement.
 *
 * The audience decides which of `client_id` or `role` is meaningful, so the
 * request refuses an announcement addressed to a tenant that names no tenant
 * rather than resolving an empty audience later in the queue.
 */
class AnnouncementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ManageAnnouncements->value) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:4000'],
            'category' => ['required', 'string', Rule::in(NotificationCategory::values())],
            'audience' => ['required', 'string', Rule::in(AnnouncementAudience::values())],
            'client_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn (): bool => $this->audience() === AnnouncementAudience::Client),
                Rule::exists('clients', 'id'),
            ],
            'role' => [
                'nullable',
                'string',
                Rule::requiredIf(fn (): bool => $this->audience() === AnnouncementAudience::Role),
                Rule::in(Role::values()),
            ],
            'severity' => ['required', 'string', Rule::in(AnnouncementSeverity::values())],
            'send_email' => ['boolean'],
            'send_now' => ['boolean'],
        ];
    }

    /**
     * The audience the composer chose.
     */
    public function audience(): ?AnnouncementAudience
    {
        return AnnouncementAudience::tryFrom((string) $this->input('audience'));
    }

    /**
     * Whether the announcement should go out as soon as it is saved.
     */
    public function sendNow(): bool
    {
        return $this->boolean('send_now');
    }
}
