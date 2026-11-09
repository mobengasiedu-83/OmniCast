<?php

namespace App\Models;

use Database\Factories\SupportTeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A team that answers support conversations.
 *
 * The team owns an inbox rather than a person: `email` is where a new enquiry is
 * announced, and the categories that name the team decide which enquiries land
 * there. Nobody has to be a member of a team to answer one, so a small platform
 * team can share the work.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $email
 * @property string|null $description
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'email', 'description', 'is_active', 'sort_order'])]
class SupportTeam extends Model
{
    /** @use HasFactory<SupportTeamFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The categories routed to this team.
     *
     * @return HasMany<SupportCategory, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(SupportCategory::class);
    }

    /**
     * The conversations assigned to this team.
     *
     * @return HasMany<SupportTicket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    /**
     * Order the teams the way the screens list them.
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }
}
