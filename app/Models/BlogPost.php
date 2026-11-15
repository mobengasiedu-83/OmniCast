<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A public marketing article managed by the platform team.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $category
 * @property string|null $excerpt
 * @property string $body
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string $status
 * @property bool $is_featured
 * @property int $sort_order
 * @property Carbon|null $published_at
 */
#[Fillable([
    'title', 'slug', 'category', 'excerpt', 'body', 'meta_title', 'meta_description',
    'status', 'is_featured', 'sort_order', 'published_at', 'created_by', 'updated_by',
])]
class BlogPost extends Model
{
    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function html(): string
    {
        return Str::markdown($this->body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', 'published')->whereNotNull('published_at');
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderByDesc('is_featured')->orderBy('sort_order')->orderByDesc('published_at');
    }
}
