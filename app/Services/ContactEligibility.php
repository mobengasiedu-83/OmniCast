<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ContactSuppression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Decides whether a contact may receive a message on a channel.
 *
 * Every uploaded contact is treated as already consented, so only the DND flag on
 * the contact and the DND / suppression registry can block a delivery.
 */
class ContactEligibility
{
    /**
     * The delivery channels this service knows how to evaluate.
     *
     * @var list<string>
     */
    public const CHANNELS = ['sms', 'voice', 'email'];

    /**
     * @param  Builder<Contact>  $query
     * @return Builder<Contact>
     */
    public function constrainEligible(Builder $query, string $channel): Builder
    {
        $identifierColumn = $channel === 'email' ? 'contacts.email' : 'contacts.phone';

        return $query
            ->where('contacts.dnd', false)
            ->whereNotExists(function ($suppressions) use ($channel, $identifierColumn): void {
                $suppressions->selectRaw('1')->from('contact_suppressions as active_suppression')
                    ->whereIn('active_suppression.channel', [$channel, ContactSuppression::ALL_CHANNELS])
                    ->where('active_suppression.is_active', true)
                    ->whereRaw('LOWER(active_suppression.identifier) = LOWER('.$identifierColumn.')')
                    ->where(function ($scope): void {
                        $scope->whereNull('active_suppression.client_id')
                            ->orWhereColumn('active_suppression.client_id', 'contacts.client_id');
                    });
            });
    }

    /**
     * Resolve channel permission and the registry evidence behind the decision.
     *
     * @return array{eligible: bool, reason: string|null, suppression_id: int|null}
     */
    public function forChannel(Contact $contact, string $channel): array
    {
        if ($contact->dnd) {
            return ['eligible' => false, 'reason' => 'dnd', 'suppression_id' => null];
        }

        $suppression = $this->suppressionFor($contact, $channel);

        if ($suppression instanceof ContactSuppression) {
            return ['eligible' => false, 'reason' => 'suppressed', 'suppression_id' => $suppression->id];
        }

        return ['eligible' => true, 'reason' => null, 'suppression_id' => null];
    }

    /**
     * @param  list<string>  $channels
     * @return array<string, array{eligible: bool, reason: string|null, suppression_id: int|null}>
     */
    public function snapshot(Contact $contact, array $channels): array
    {
        $snapshot = [];

        foreach ($channels as $channel) {
            $snapshot[$channel] = $this->forChannel($contact, $channel);
        }

        return $snapshot;
    }

    /**
     * Resolve snapshots for a batch without issuing queries per contact.
     *
     * @param  Collection<int, Contact>  $contacts
     * @param  list<string>  $channels
     * @return array<int, array<string, array{eligible: bool, reason: string|null, suppression_id: int|null}>>
     */
    public function snapshots(Collection $contacts, array $channels): array
    {
        $identifiers = $contacts->flatMap(fn (Contact $contact): array => array_filter([
            mb_strtolower($contact->phone),
            $contact->email === null ? null : mb_strtolower($contact->email),
        ]))->unique()->values();

        $suppressions = ContactSuppression::query()
            ->whereIn('channel', [...$channels, ContactSuppression::ALL_CHANNELS])
            ->where('is_active', true)
            ->whereIn('identifier', $identifiers)
            ->where(fn ($query) => $query->whereNull('client_id')->orWhere('client_id', $contacts->first()?->client_id))
            ->get()
            ->keyBy(fn (ContactSuppression $suppression): string => $suppression->channel.':'.mb_strtolower($suppression->identifier));

        $snapshots = [];

        foreach ($contacts as $contact) {
            foreach ($channels as $channel) {
                $identifier = $channel === 'email' ? $contact->email : $contact->phone;
                $key = $identifier === null ? null : mb_strtolower($identifier);
                $suppression = $key === null
                    ? null
                    : ($suppressions->get($channel.':'.$key) ?? $suppressions->get(ContactSuppression::ALL_CHANNELS.':'.$key));
                $reason = match (true) {
                    (bool) $contact->dnd => 'dnd',
                    $suppression instanceof ContactSuppression => 'suppressed',
                    default => null,
                };

                $snapshots[$contact->id][$channel] = [
                    'eligible' => $reason === null,
                    'reason' => $reason,
                    'suppression_id' => $suppression?->id,
                ];
            }
        }

        return $snapshots;
    }

    /**
     * The active registry entry blocking an address on a channel, if any.
     */
    protected function suppressionFor(Contact $contact, string $channel): ?ContactSuppression
    {
        $identifier = $channel === 'email'
            ? mb_strtolower((string) $contact->email)
            : mb_strtolower((string) $contact->phone);

        if ($identifier === '') {
            return null;
        }

        return ContactSuppression::query()
            ->whereIn('channel', [$channel, ContactSuppression::ALL_CHANNELS])
            ->where('is_active', true)
            ->whereRaw('LOWER(identifier) = ?', [$identifier])
            ->where(fn ($query) => $query->whereNull('client_id')->orWhere('client_id', $contact->client_id))
            ->orderByDesc('id')
            ->first();
    }
}
