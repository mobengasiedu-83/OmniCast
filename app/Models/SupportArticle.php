<?php

namespace App\Models;

use Database\Factories\SupportArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A help centre article.
 *
 * One library answers both audiences: the public help centre lists published
 * articles, and a signed-in client reads the same text from the support menu. The
 * body is markdown, rendered on the server so the page is readable before React
 * mounts.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $category
 * @property string|null $excerpt
 * @property string $body
 * @property bool $is_published
 * @property bool $is_featured
 * @property int $sort_order
 * @property int $views
 * @property Carbon|null $published_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'title', 'slug', 'category', 'excerpt', 'body', 'is_published', 'is_featured',
    'sort_order', 'views', 'published_at', 'created_by', 'updated_by',
])]
class SupportArticle extends Model
{
    /** @use HasFactory<SupportArticleFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'views' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * The platform user who first wrote the article.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The platform user who last saved the article.
     *
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * The article text rendered to HTML for the help centre.
     */
    public function html(): string
    {
        return Str::markdown($this->body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * Only the articles the help centre publishes.
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * Order the library: featured first, then the operator's order.
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('title');
    }

    /**
     * Match a free-text search against the title, summary and body.
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $like = '%'.$term.'%';

        $query->where(fn (Builder $query) => $query
            ->where('title', 'like', $like)
            ->orWhere('excerpt', 'like', $like)
            ->orWhere('body', 'like', $like));
    }
}
