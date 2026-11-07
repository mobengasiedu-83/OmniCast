<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\MessageTemplate;
use App\Models\User;

/**
 * Authorises template library actions.
 *
 * A tenant may read the platform catalogue but never rewrite it: "customising"
 * a catalogue template copies it into the tenant first, and the copy is the row
 * this policy then lets the tenant edit.
 */
class MessageTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewTemplates->value);
    }

    public function view(User $user, MessageTemplate $template): bool
    {
        return $this->viewAny($user) && $this->visible($user, $template);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageTemplates->value);
    }

    public function update(User $user, MessageTemplate $template): bool
    {
        return $user->can(Permission::ManageTemplates->value)
            && $this->visible($user, $template)
            && $template->isEditableBy($user);
    }

    public function delete(User $user, MessageTemplate $template): bool
    {
        return $this->update($user, $template);
    }

    public function approve(User $user, MessageTemplate $template): bool
    {
        return $user->can(Permission::ApproveTemplates->value)
            && $template->status->canBeReviewed();
    }

    /**
     * Whether the platform may maintain the shared catalogue.
     *
     * Catalogue rows are platform property: the platform keeps control of them
     * after they stop being offered, which is what lets it rename or retire a
     * template the clients have already copied.
     */
    public function manageCatalogue(User $user, MessageTemplate $template): bool
    {
        return $user->client_id === null
            && $template->client_id === null
            && $user->can(Permission::ManageTemplates->value);
    }

    /**
     * Whether the catalogue template or the tenant's own copy is in scope.
     */
    protected function visible(User $user, MessageTemplate $template): bool
    {
        return $template->client_id === null
            || $user->client_id === null
            || $user->client_id === $template->client_id;
    }
}
