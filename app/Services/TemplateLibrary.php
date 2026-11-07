<?php

namespace App\Services;

use App\Enums\TemplateStatus;
use App\Models\Campaign;
use App\Models\MessageTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * The rules of the template library.
 *
 * A client browses the platform catalogue together with the copies its own
 * tenant owns, but it may only ever edit its own copies: the catalogue is
 * duplicated into the tenant first. Keeping that rule here means the campaign
 * editor and the library page cannot drift apart.
 */
class TemplateLibrary
{
    /**
     * Every template the user may see: the platform catalogue plus the copies
     * owned by the user's tenant.
     *
     * @return Builder<MessageTemplate>
     */
    public function query(User $user): Builder
    {
        return MessageTemplate::query()->visibleTo($user->client_id);
    }

    /**
     * The templates that may be picked in a campaign, which leaves out the
     * copies an operator has archived or that are still waiting for review.
     *
     * @return Builder<MessageTemplate>
     */
    public function usable(User $user): Builder
    {
        return $this->query($user)
            ->whereIn('status', [TemplateStatus::Draft->value, TemplateStatus::Active->value]);
    }

    /**
     * Copy a template into the user's tenant so the copy can be edited without
     * touching the catalogue everybody else sees.
     */
    public function customise(MessageTemplate $template, User $user): MessageTemplate
    {
        return $this->duplicate($template, $user, $template->name);
    }

    /**
     * Duplicate a template, keeping a trail back to the copy it came from.
     */
    public function duplicate(MessageTemplate $template, User $user, ?string $name = null): MessageTemplate
    {
        $copy = $template->replicate([
            'usage_count', 'created_by', 'approved_by', 'approved_at', 'rejection_reason',
        ]);

        $copy->fill([
            'client_id' => $user->client_id,
            'origin_template_id' => $template->id,
            'name' => $this->uniqueName($user->client_id, $name ?? $template->name),
            'status' => TemplateStatus::Draft,
            'created_by' => $user->id,
        ])->save();

        return $copy;
    }

    /**
     * Add or remove the template from the operator's shortlist.
     *
     * @return bool Whether the template is now a favourite.
     */
    public function toggleFavourite(MessageTemplate $template, User $user): bool
    {
        $changes = $template->favouritedBy()->toggle($user->id);

        return in_array($user->id, $changes['attached'] ?? [], true);
    }

    /**
     * Count one more campaign against a template, so the library can show which
     * starting points clients actually reach for.
     */
    public function recordUsage(MessageTemplate $template): void
    {
        MessageTemplate::withTrashed()->whereKey($template->getKey())->increment('usage_count');

        $template->usage_count = (int) $template->usage_count + 1;
    }

    /**
     * Record the template a freshly saved campaign started from.
     */
    public function recordCampaignUsage(Campaign $campaign): void
    {
        if ($campaign->message_template_id === null) {
            return;
        }

        MessageTemplate::withTrashed()
            ->whereKey($campaign->message_template_id)
            ->increment('usage_count');
    }

    /**
     * A name that is free inside the tenant, so the library never shows two
     * identical entries side by side.
     */
    protected function uniqueName(?int $clientId, string $name): string
    {
        $base = Str::limit(trim($name), 100, '');
        $candidate = $base;
        $suffix = 2;

        while (MessageTemplate::withTrashed()
            ->where('client_id', $clientId)
            ->where('name', $candidate)
            ->exists()) {
            $candidate = Str::limit($base, 92, '')." ({$suffix})";
            $suffix++;
        }

        return $candidate;
    }
}
