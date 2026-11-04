<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ContactSegment;
use Illuminate\Database\Eloquent\Builder;

class ContactSegmentQuery
{
    /**
     * Apply only the supported, tenant-safe saved segment filters.
     *
     * @param  Builder<Contact>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Contact>
     */
    public function apply(Builder $query, array $filters, int $clientId): Builder
    {
        $query->where('client_id', $clientId);

        if (filled($filters['search'] ?? null)) {
            $query->search((string) $filters['search']);
        }

        if (array_key_exists('dnd', $filters) && $filters['dnd'] !== null && $filters['dnd'] !== '') {
            $query->where('dnd', filter_var($filters['dnd'], FILTER_VALIDATE_BOOLEAN));
        }

        foreach (['location', 'company'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, 'like', '%'.addcslashes((string) $filters[$field], '%_').'%');
            }
        }

        if (isset($filters['contact_list_id'])) {
            $query->whereHas('contactLists', fn (Builder $lists) => $lists->whereKey($filters['contact_list_id']));
        }

        if (isset($filters['tag_id'])) {
            $query->whereHas('tags', fn (Builder $tags) => $tags->whereKey($filters['tag_id']));
        }

        if (array_key_exists('has_email', $filters)) {
            $filters['has_email'] ? $query->whereNotNull('email') : $query->whereNull('email');
        }

        return $query;
    }

    public function count(ContactSegment $segment): int
    {
        return $this->apply(Contact::query(), $segment->filters, $segment->client_id)->count();
    }
}
