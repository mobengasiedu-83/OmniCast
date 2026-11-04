<?php

namespace App\Services;

use App\Models\CampaignContact;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Finds contacts that share an email address and merges a duplicate into a survivor
 * without losing list membership, tags or campaign recipients.
 */
class ContactDuplicates
{
    /**
     * Email addresses used by more than one contact in the tenant.
     *
     * @return list<array{
     *     email: string,
     *     contacts: list<array{id: int, name: string, phone: string, dnd: bool, created_at: string|null}>
     * }>
     */
    public function groups(int $clientId, int $limit = 25): array
    {
        $emails = $this->duplicateEmails($clientId, $limit);

        if ($emails === []) {
            return [];
        }

        $contacts = Contact::query()
            ->where('client_id', $clientId)
            ->whereIn(DB::raw('LOWER(email)'), $emails)
            ->orderBy('id')
            ->get();

        $groups = [];

        foreach ($emails as $email) {
            $members = array_values($contacts
                ->filter(fn (Contact $contact): bool => mb_strtolower((string) $contact->email) === $email)
                ->map(fn (Contact $contact): array => [
                    'id' => $contact->id,
                    'name' => $contact->full_name,
                    'phone' => $contact->phone,
                    'dnd' => (bool) $contact->dnd,
                    'created_at' => $contact->created_at?->toIso8601String(),
                ])
                ->all());

            $groups[] = ['email' => $email, 'contacts' => $members];
        }

        return $groups;
    }

    /**
     * How many email addresses are shared by more than one contact.
     */
    public function emailGroupCount(int $clientId): int
    {
        $duplicateGroups = DB::table('contacts')
            ->where('client_id', $clientId)
            ->whereNull('deleted_at')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->selectRaw('LOWER(email) as duplicate_key')
            ->groupBy('duplicate_key')
            ->havingRaw('COUNT(*) > 1');

        return (int) DB::query()->fromSub($duplicateGroups, 'duplicate_groups')->count();
    }

    /**
     * How many contacts would disappear if every duplicate group was merged.
     */
    public function duplicateContactCount(int $clientId): int
    {
        $groups = DB::table('contacts')
            ->where('client_id', $clientId)
            ->whereNull('deleted_at')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->selectRaw('LOWER(email) as duplicate_key, COUNT(*) as contacts_count')
            ->groupBy('duplicate_key')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        return $groups->sum(fn (object $group): int => ((int) $group->contacts_count) - 1);
    }

    /**
     * Merge a duplicate contact into the survivor.
     */
    public function merge(Contact $survivor, Contact $duplicate, ?User $actor = null): Contact
    {
        if ($survivor->is($duplicate) || $survivor->client_id !== $duplicate->client_id) {
            throw new InvalidArgumentException('Both contacts must belong to the same client account.');
        }

        $survivor->fill($this->missingAttributes($survivor, $duplicate))->save();

        foreach ($duplicate->contactLists()->pluck('contact_lists.id')->all() as $listId) {
            $survivor->contactLists()->syncWithoutDetaching([(int) $listId => ['added_at' => now()]]);
        }

        $survivor->tags()->syncWithoutDetaching($duplicate->tags()->pluck('tags.id')->all());

        $this->moveCampaignContacts($survivor, $duplicate);

        $duplicateId = $duplicate->id;
        $duplicate->delete();

        activity()
            ->causedBy($actor)
            ->performedOn($survivor)
            ->withProperties(['merged_contact_id' => $duplicateId])
            ->log('contact merged');

        return $survivor;
    }

    /**
     * Fill the survivor's empty fields from the duplicate without overwriting data.
     *
     * @return array<string, mixed>
     */
    protected function missingAttributes(Contact $survivor, Contact $duplicate): array
    {
        $attributes = [];

        foreach (['last_name', 'email', 'company', 'account_number', 'customer_id', 'location', 'alternative_phone'] as $field) {
            if (blank($survivor->getAttribute($field)) && filled($duplicate->getAttribute($field))) {
                $attributes[$field] = $duplicate->getAttribute($field);
            }
        }

        $customFields = array_replace($duplicate->custom_fields ?? [], $survivor->custom_fields ?? []);

        if ($customFields !== []) {
            $attributes['custom_fields'] = $customFields;
        }

        if (! $survivor->dnd && $duplicate->dnd) {
            $attributes['dnd'] = true;
            $attributes['dnd_at'] = $duplicate->dnd_at ?? now();
        }

        return $attributes;
    }

    /**
     * Repoint campaign recipients, dropping rows the survivor already has.
     */
    protected function moveCampaignContacts(Contact $survivor, Contact $duplicate): void
    {
        $survivorCampaigns = CampaignContact::query()
            ->where('contact_id', $survivor->id)
            ->pluck('campaign_id')
            ->all();

        CampaignContact::query()
            ->where('contact_id', $duplicate->id)
            ->whereIn('campaign_id', $survivorCampaigns)
            ->delete();

        CampaignContact::query()
            ->where('contact_id', $duplicate->id)
            ->update(['contact_id' => $survivor->id]);
    }

    /**
     * @return list<string>
     */
    protected function duplicateEmails(int $clientId, int $limit): array
    {
        return array_values(DB::table('contacts')
            ->where('client_id', $clientId)
            ->whereNull('deleted_at')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->selectRaw('LOWER(email) as duplicate_key')
            ->groupBy('duplicate_key')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit($limit)
            ->pluck('duplicate_key')
            ->map(fn (mixed $email): string => (string) $email)
            ->all());
    }
}
