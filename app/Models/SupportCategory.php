<?php

namespace App\Models;

use App\Enums\SupportCategoryAudience;
use App\Enums\SupportPriority;
use App\Enums\SupportTicketType;
use Database\Factories\SupportCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The reason someone is writing, and the team that answers it.
 *
 * A category is configuration rather than code: an operator adds "Wallet top-up"
 * in the admin screen, points it at the Finance team, and it appears on both the
 * public contact form and the client ticket form with the priority it carries.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property SupportCategoryAudience $audience
 * @property int|null $support_team_id
 * @property SupportPriority $default_priority
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SupportTeam|null $team
 */
#[Fillable([
    'name', 'slug', 'description', 'audience', 'support_team_id',
    'default_priority', 'is_active', 'sort_order',
])]
class SupportCategory extends Model
{
    /** @use HasFactory<SupportCategoryFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience' => SupportCategoryAudience::class,
            'default_priority' => SupportPriority::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The team that answers conversations in this category.
     *
     * @return BelongsTo<SupportTeam, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(SupportTeam::class, 'support_team_id');
    }

    /**
     * The conversations filed under this category.
     *
     * @return HasMany<SupportTicket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    /**
     * The categories a given form offers.
     */
    #[Scope]
    protected function forType(Builder $query, SupportTicketType $type): void
    {
        $audiences = array_values(array_filter(
            SupportCategoryAudience::cases(),
            fn (SupportCategoryAudience $audience): bool => $audience->covers($type),
        ));

        $query->whereIn('audience', array_map(
            fn (SupportCategoryAudience $audience): string => $audience->value,
            $audiences,
        ));
    }

    /**
     * Order the categories the way the forms and screens list them.
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }
}
