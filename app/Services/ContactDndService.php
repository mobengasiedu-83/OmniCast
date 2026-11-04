<?php

namespace App\Services;

use App\Enums\ContactSuppressionReason;
use App\Models\Contact;
use App\Models\ContactSuppression;
use App\Models\User;

/**
 * Keeps the contact level DND flag in step with the DND registry.
 *
 * Uploaded contacts are assumed to have consented already, so the registry only
 * has to remember the numbers that asked not to be disturbed. Storing the number
 * in the registry means a re-import cannot silently resurrect a blocked record.
 */
class ContactDndService
{
    /**
     * Turn DND on for a contact and block the number in the registry.
     */
    public function apply(Contact $contact, string $source = 'staff', ?User $actor = null): void
    {
        $contact->forceFill([
            'dnd' => true,
            'dnd_at' => $contact->dnd_at ?? now(),
        ])->save();

        ContactSuppression::query()->updateOrCreate(
            [
                'client_id' => $contact->client_id,
                'channel' => ContactSuppression::ALL_CHANNELS,
                'identifier' => $this->identifier($contact),
                'is_active' => true,
            ],
            [
                'reason' => ContactSuppressionReason::Dnd->value,
                'source' => mb_substr($source, 0, 64),
                'recorded_by' => $actor?->id,
                'suppressed_at' => now(),
                'lifted_at' => null,
            ],
        );
    }

    /**
     * Lift a DND decision that this client made for the contact's number.
     *
     * Platform wide DND entries and entries with another reason stay in place.
     */
    public function lift(Contact $contact): void
    {
        $contact->forceFill([
            'dnd' => false,
            'dnd_at' => null,
        ])->save();

        ContactSuppression::query()
            ->where('client_id', $contact->client_id)
            ->where('channel', ContactSuppression::ALL_CHANNELS)
            ->where('identifier', $this->identifier($contact))
            ->where('reason', ContactSuppressionReason::Dnd->value)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'lifted_at' => now(),
            ]);
    }

    /**
     * Apply or lift DND from a single boolean coming from a form or an import.
     */
    public function sync(Contact $contact, bool $dnd, string $source = 'staff', ?User $actor = null): void
    {
        if ($dnd) {
            $this->apply($contact, $source, $actor);

            return;
        }

        $this->lift($contact);
    }

    /**
     * The registry key for a contact: the MSISDN, lower-cased like the registry stores it.
     */
    protected function identifier(Contact $contact): string
    {
        return mb_strtolower($contact->phone);
    }
}
