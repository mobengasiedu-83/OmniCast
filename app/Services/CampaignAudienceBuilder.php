<?php

namespace App\Services;

use App\Enums\CampaignContactStatus;
use App\Enums\Channel;
use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\Contact;
use App\Models\ContactSegment;
use Illuminate\Database\Eloquent\Builder;

/**
 * Decides who a campaign is allowed to broadcast to and materialises that audience.
 */
class CampaignAudienceBuilder
{
    public function __construct(protected ContactEligibility $eligibility) {}

    /**
     * The contacts that may receive the campaign: broadcastable and, when a list is
     * attached, members of that list.
     *
     * @return list<int>
     */
    public function eligibleContactIds(Campaign $campaign): array
    {
        $query = Contact::query()->where('client_id', $campaign->client_id);

        $channels = array_map(fn (Channel $channel): string => $channel->value, $campaign->channel->deliveryChannels());
        $query->where(function (Builder $query) use ($channels): void {
            foreach ($channels as $channel) {
                $query->orWhere(fn (Builder $channelQuery) => $this->eligibility->constrainEligible($channelQuery, $channel));
            }
        });

        if ($campaign->contact_segment_id !== null) {
            $segment = ContactSegment::query()->where('client_id', $campaign->client_id)->find($campaign->contact_segment_id);
            if ($segment === null) {
                return [];
            }
            app(ContactSegmentQuery::class)->apply($query, $segment->filters, $campaign->client_id);
        } elseif ($campaign->contact_list_id !== null) {
            $query->whereHas('contactLists', function (Builder $query) use ($campaign): void {
                $query->whereKey($campaign->contact_list_id);
            });
        }

        return array_values($query->pluck('contacts.id')->map(fn (mixed $id): int => (int) $id)->all());
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
        $channels = array_map(fn (Channel $channel): string => $channel->value, $campaign->channel->deliveryChannels());
        $snapshots = [];
        Contact::query()->whereIn('id', $newContactIds)->chunkById(500, function ($contacts) use (&$snapshots, $channels): void {
            $snapshots += $this->eligibility->snapshots($contacts, $channels);
        });

        foreach (array_chunk($newContactIds, 500) as $chunk) {
            $now = now();

            CampaignContact::query()->insert(array_map(fn (int $contactId): array => [
                'campaign_id' => $campaign->id,
                'contact_id' => $contactId,
                'recipient_snapshot' => json_encode($snapshots[$contactId] ?? [], JSON_THROW_ON_ERROR),
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
