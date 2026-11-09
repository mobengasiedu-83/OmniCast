<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportArticle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The help centre library, written and published from the admin.
 *
 * Publishing an article puts it on the public help centre and in the signed-in
 * support menu at the same moment. The body is markdown, converted on the server
 * so the reader gets a finished page.
 */
class SupportArticleController extends Controller
{
    /**
     * List every article, drafts included.
     */
    public function index(): Response
    {
        $articles = SupportArticle::query()
            ->ordered()
            ->get()
            ->map(fn (SupportArticle $article): array => [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'category' => $article->category,
                'excerpt' => $article->excerpt,
                'body' => $article->body,
                'is_published' => $article->is_published,
                'is_featured' => $article->is_featured,
                'sort_order' => $article->sort_order,
                'views' => $article->views,
                'published_at' => $article->published_at?->toIso8601String(),
                'updated_at' => $article->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/support/knowledge-base', [
            'articles' => $articles,
            'stats' => [
                'total' => SupportArticle::query()->count(),
                'published' => SupportArticle::query()->published()->count(),
                'drafts' => SupportArticle::query()->where('is_published', false)->count(),
                'views' => (int) SupportArticle::query()->sum('views'),
            ],
        ]);
    }

    /**
     * Write an article.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateArticle($request);

        SupportArticle::query()->create([
            ...$validated,
            'slug' => $this->slug($validated['title']),
            'published_at' => $validated['is_published'] ? now() : null,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Article created.']);
    }

    /**
     * Save an article, including its published state.
     */
    public function update(Request $request, SupportArticle $article): RedirectResponse
    {
        $validated = $this->validateArticle($request);

        $newlyPublished = $validated['is_published'] && ! $article->is_published;

        $article->forceFill([
            ...$validated,
            'published_at' => $validated['is_published'] ? ($article->published_at ?? now()) : null,
            'updated_by' => $request->user()->id,
        ])->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => $newlyPublished ? 'Article published to the help centre.' : 'Article saved.',
        ]);
    }

    /**
     * Delete an article.
     */
    public function destroy(SupportArticle $article): RedirectResponse
    {
        $article->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Article deleted.']);
    }

    /**
     * The rules shared by writing and saving.
     *
     * @return array<string, mixed>
     */
    protected function validateArticle(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:80'],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string', 'min:20'],
            'is_published' => ['required', 'boolean'],
            'is_featured' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'body.required' => 'Write the article body before saving.',
            'body.min' => 'An article needs at least a short paragraph.',
        ]);
    }

    /**
     * A slug that is stable and unique, derived from the title.
     */
    protected function slug(string $title): string
    {
        $base = Str::slug($title) ?: 'article';
        $slug = $base;
        $suffix = 2;

        while (SupportArticle::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
