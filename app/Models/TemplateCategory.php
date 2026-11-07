<?php

namespace App\Models;

use Database\Factories\TemplateCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $client_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $sort_order
 */
#[Fillable(['client_id', 'name', 'slug', 'description', 'sort_order'])]
class TemplateCategory extends Model
{
    /** @use HasFactory<TemplateCategoryFactory> */
    use HasFactory;

    /**
     * Derive the slug from the name so two categories cannot collide inside the
     * same tenant, and a renamed category keeps a readable identifier.
     */
    protected static function booted(): void
    {
        static::saving(function (TemplateCategory $category): void {
            $category->slug = $category->uniqueSlug($category->slug ?: $category->name);
        });
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return HasMany<MessageTemplate, $this>
     */
    public function templates(): HasMany
    {
        return $this->hasMany(MessageTemplate::class, 'template_category_id');
    }

    /**
     * Whether this is a platform category rather than a tenant one.
     */
    public function isSystem(): bool
    {
        return $this->client_id === null;
    }

    /**
     * Whether the account may see this category in its library.
     */
    #[Scope]
    protected function visibleTo(Builder $query, ?int $clientId): void
    {
        $query->where(function (Builder $query) use ($clientId): void {
            $query->whereNull($query->qualifyColumn('client_id'));

            if ($clientId !== null) {
                $query->orWhere($query->qualifyColumn('client_id'), $clientId);
            }
        });
    }

    /**
     * A slug that is unique for the tenant, suffixed when the name is taken.
     */
    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $suffix = 2;

        while (static::query()
            ->where('client_id', $this->client_id)
            ->where('slug', $slug)
            ->when($this->exists, fn (Builder $query) => $query->whereKeyNot($this->getKey()))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
