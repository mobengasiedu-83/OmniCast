<?php

namespace App\Services;

use App\Enums\CampaignContactStatus;
use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;

/**
 * Decides who a campaign is allowed to broadcast to and materialises that audience.
 */
class CampaignAudienceBuilder
{
    /**
     * The contacts that may receive the campaign: broadcastable and, when a list is
     * attached, members of that list.
     *
     * @return list<int>
     */
    public function eligibleContactIds(Campaign $campaign): array
    {
        $query = Contact::query()
            ->where('client_id', $campaign->client_id)
            ->broadcastable();

        if ($campaign->contact_list_id !== null) {
            $query->whereHas('contactLists', function (Builder $query) use ($campaign): void {
                $query->whereKey($campaign->contact_list_id);
            });
        }

        /** @var list<int> $ids */
        $ids = $query->pluck('id')->all();

        return $ids;
    }

    /**
     * Count the recipients the campaign will broadcast to.
     */
    public function count(Campaign $campaign): int
    {
        return count($this->eligibleContactIds($campaign));
    }

    /**
     * Create the campaign_contacts rows for the eligible audience that are not queued yet.
     *
     * @return int the total number of recipients on the campaign after building
     */
    public function build(Campaign $campaign): int
    {
        $eligible = $this->eligibleContactIds($campaign);

        $alreadyQueued = CampaignContact::query()
            ->where('campaign_id', $campaign->id)
            ->pluck('contact_id')
            ->all();

        $newContactIds = array_values(array_diff($eligible, $alreadyQueued));

        foreach (array_chunk($newContactIds, 500) as $chunk) {
            $now = now();

            CampaignContact::query()->insert(array_map(fn (int $contactId): array => [
                'campaign_id' => $campaign->id,
                'contact_id' => $contactId,
                'status' => CampaignContactStatus::Pending->value,
                'attempts' => 0,
                'cost' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }

        $total = CampaignContact::query()->where('campaign_id', $campaign->id)->count();

        $campaign->forceFill(['estimated_recipients' => $total])->save();

        return $total;
    }
}
