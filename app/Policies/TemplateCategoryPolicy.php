<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\TemplateCategory;
use App\Models\User;

/**
 * Authorises category management.
 *
 * Platform categories shape the catalogue every tenant sees, so only platform
 * users may change them; a tenant edits only the categories it created.
 */
class TemplateCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewTemplates->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageTemplateCategories->value);
    }

    public function update(User $user, TemplateCategory $category): bool
    {
        return $this->create($user) && $this->owns($user, $category);
    }

    public function delete(User $user, TemplateCategory $category): bool
    {
        return $this->update($user, $category);
    }

    protected function owns(User $user, TemplateCategory $category): bool
    {
        return $category->client_id === null
            ? $user->client_id === null
            : $user->client_id === null || $user->client_id === $category->client_id;
    }
}
