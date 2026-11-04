<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Contact;
use App\Models\ContactSuppression;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aggregates the contact book health numbers shown on the audiences screen.
 */
class ContactQualityReport
{
    public function __construct(
        protected ContactEligibility $eligibility,
        protected ContactDuplicates $duplicates,
    ) {}

    /**
     * @return array{
     *     total: int,
     *     broadcastable: int,
     *     dnd: int,
     *     missing_email: int,
     *     invalid_email: int,
     *     duplicate_emails: int,
     *     duplicate_contacts: int,
     *     suppressed: int,
     *     added_last_30_days: int,
     *     channels: list<array{channel: string, eligible: int, blocked: int}>
     * }
     */
    public function summary(Client $client): array
    {
        $base = fn (): Builder => Contact::query()->where('contacts.client_id', $client->id);
        $total = $base()->count();
        $channels = [];

        foreach (ContactEligibility::CHANNELS as $channel) {
            $eligible = $this->eligibility->constrainEligible($base(), $channel)->count();

            $channels[] = [
                'channel' => $channel,
                'eligible' => $eligible,
                'blocked' => $total - $eligible,
            ];
        }

        return [
            'total' => $total,
            'broadcastable' => $base()->broadcastable()->count(),
            'dnd' => $base()->where('dnd', true)->count(),
            'missing_email' => $base()->where(fn ($query) => $query->whereNull('email')->orWhere('email', ''))->count(),
            'invalid_email' => $base()->whereNotNull('email')->where('email', '!=', '')
                ->whereRaw("LOWER(email) NOT LIKE '%_@_%._%'")->count(),
            'duplicate_emails' => $this->duplicates->emailGroupCount($client->id),
            'duplicate_contacts' => $this->duplicates->duplicateContactCount($client->id),
            'suppressed' => ContactSuppression::query()
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('client_id')->orWhere('client_id', $client->id))
                ->count(),
            'added_last_30_days' => $base()->where('created_at', '>=', now()->subDays(30))->count(),
            'channels' => $channels,
        ];
    }
}
