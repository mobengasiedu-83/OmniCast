<?php

namespace App\Services\Support;

use App\Enums\SupportTicketType;
use App\Models\SupportArticle;
use App\Models\SupportCategory;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shapes the shared support content for the three front ends.
 *
 * The public contact page, the help centre, the client support menu and the
 * admin library all read the same categories and articles; this service keeps the
 * shape they receive identical, so a new field is added once.
 */
class SupportLibrary
{
    public function __construct(protected SupportSettings $settings) {}

    /**
     * The categories a given form offers.
     *
     * @return list<array{slug: string, name: string, description: string|null, priority: string, priority_label: string}>
     */
    public function categories(SupportTicketType $type): array
    {
        return SupportCategory::query()
            ->where('is_active', true)
            ->forType($type)
            ->ordered()
            ->get()
            ->map(fn (SupportCategory $category): array => [
                'slug' => $category->slug,
                'name' => $category->name,
                'description' => $category->description,
                'priority' => $category->default_priority->value,
                'priority_label' => $category->default_priority->label(),
            ])
            ->all();
    }

    /**
     * The contact details the business has confirmed, if any.
     *
     * @return array{
     *     email: string|null,
     *     support_email: string|null,
     *     sales_email: string|null,
     *     phone: string|null,
     *     whatsapp: string|null,
     *     address: string|null,
     *     hours: string|null,
     *     response_time: string|null,
     *     status_url: string|null
     * }
     */
    public function contact(): array
    {
        return [
            'email' => $this->settings->contactEmail(),
            'support_email' => $this->settings->supportEmail(),
            'sales_email' => $this->settings->salesEmail(),
            'phone' => $this->settings->phone(),
            'whatsapp' => $this->settings->whatsapp(),
            'address' => $this->settings->officeAddress(),
            'hours' => $this->settings->openingHours(),
            'response_time' => $this->settings->responseTime(),
            'status_url' => $this->settings->statusUrl(),
        ];
    }

    /**
     * The published library, optionally narrowed by a search term.
     *
     * @return list<array{slug: string, title: string, excerpt: string|null, category: string|null, is_featured: bool}>
     */
    public function articles(?string $search = null, int $limit = 100): array
    {
        return SupportArticle::query()
            ->published()
            ->when($search !== null && $search !== '', fn (Builder $query) => $query->search($search))
            ->ordered()
            ->limit($limit)
            ->get()
            ->map(fn (SupportArticle $article): array => [
                'slug' => $article->slug,
                'title' => $article->title,
                'excerpt' => $article->excerpt,
                'category' => $article->category,
                'is_featured' => $article->is_featured,
            ])
            ->all();
    }

    /**
     * A single article, rendered for reading.
     *
     * @return array{slug: string, title: string, excerpt: string|null, category: string|null, html: string, updated_at: string|null}
     */
    public function article(SupportArticle $article): array
    {
        return [
            'slug' => $article->slug,
            'title' => $article->title,
            'excerpt' => $article->excerpt,
            'category' => $article->category,
            'html' => $article->html(),
            'updated_at' => $article->updated_at?->toIso8601String(),
        ];
    }

    /**
     * The articles pinned to the top of a library page.
     *
     * @return list<array{slug: string, title: string, excerpt: string|null, category: string|null, is_featured: bool}>
     */
    public function featured(int $limit = 3): array
    {
        return SupportArticle::query()
            ->published()
            ->where('is_featured', true)
            ->ordered()
            ->limit($limit)
            ->get()
            ->map(fn (SupportArticle $article): array => [
                'slug' => $article->slug,
                'title' => $article->title,
                'excerpt' => $article->excerpt,
                'category' => $article->category,
                'is_featured' => true,
            ])
            ->all();
    }

    /**
     * What a form tells the visitor about attachments before they upload one.
     *
     * @return array{max_files: int, max_kilobytes: int, mimes: list<string>}
     */
    public function attachmentLimits(): array
    {
        return [
            'max_files' => (int) config('support.attachments.max_files', 3),
            'max_kilobytes' => (int) config('support.attachments.max_kilobytes', 5120),
            'mimes' => array_values((array) config('support.attachments.mimes', [])),
        ];
    }
}
