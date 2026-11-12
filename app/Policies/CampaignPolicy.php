<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Campaign;
use App\Models\User;

/**
 * Authorises campaign actions for the client tenant the user belongs to.
 */
class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewCampaigns->value);
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $this->viewAny($user) && $this->sameClient($user, $campaign);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageCampaigns->value);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->can(Permission::ManageCampaigns->value)
            && $this->sameClient($user, $campaign)
            && $campaign->status->isEditable();
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $this->update($user, $campaign);
    }

    public function submit(User $user, Campaign $campaign): bool
    {
        return $user->can(Permission::ManageCampaigns->value)
            && $this->sameClient($user, $campaign)
            && $campaign->status->canBeSubmitted();
    }

    public function approve(User $user, Campaign $campaign): bool
    {
        return $user->can(Permission::ApproveCampaigns->value)
            && $this->sameClient($user, $campaign)
            && $campaign->status->canBeApproved();
    }

    /**
     * Voice audio is reviewed by the same team that approves campaigns.
     */
    public function reviewAudio(User $user, Campaign $campaign): bool
    {
        return $user->can(Permission::ApproveCampaigns->value)
            && $this->sameClient($user, $campaign)
            && $campaign->requiresAudioApproval()
            && $campaign->audio_status->canBeReviewed()
            && ! $campaign->status->isTerminal();
    }

    public function launch(User $user, Campaign $campaign): bool
    {
        return $user->can(Permission::LaunchCampaigns->value)
            && $this->sameClient($user, $campaign)
            && $campaign->client?->canBroadcast() === true
            && $campaign->status->canBeScheduled();
    }

    public function cancel(User $user, Campaign $campaign): bool
    {
        return $user->can(Permission::ManageCampaigns->value)
            && $this->sameClient($user, $campaign)
            && ! $campaign->status->isTerminal();
    }

    public function duplicate(User $user, Campaign $campaign): bool
    {
        return $user->can(Permission::ManageCampaigns->value)
            && $this->sameClient($user, $campaign);
    }

    protected function sameClient(User $user, Campaign $campaign): bool
    {
        return $user->client_id === null || $user->client_id === $campaign->client_id;
    }
}
