<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BlogPostController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/blog/index', [
            'posts' => BlogPost::query()->orderByDesc('updated_at')->get(),
            'stats' => [
                'total' => BlogPost::query()->count(),
                'published' => BlogPost::query()->published()->count(),
                'drafts' => BlogPost::query()->where('status', 'draft')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedPost($request);
        $status = $validated['status'];

        BlogPost::query()->create([
            ...$validated,
            'slug' => $this->uniqueSlug($validated['slug'] ?: $validated['title']),
            'published_at' => $status === 'published' ? now() : null,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Blog article created.']);
    }

    public function update(Request $request, BlogPost $blogPost): RedirectResponse
    {
        $validated = $this->validatedPost($request, $blogPost);
        $wasPublished = $blogPost->status === 'published';
        $isPublished = $validated['status'] === 'published';

        $blogPost->forceFill([
            ...$validated,
            'slug' => $this->uniqueSlug($validated['slug'], $blogPost),
            'published_at' => $isPublished ? ($blogPost->published_at ?? now()) : null,
            'updated_by' => $request->user()->id,
        ])->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => $isPublished && ! $wasPublished ? 'Article published.' : 'Article saved.',
        ]);
    }

    public function destroy(BlogPost $blogPost): RedirectResponse
    {
        $blogPost->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Blog article deleted.']);
    }

    /** @return array<string, mixed> */
    private function validatedPost(Request $request, ?BlogPost $blogPost = null): array
    {
        $request->merge([
            'slug' => Str::slug((string) ($request->input('slug') ?: $request->input('title'))),
        ]);

        $slugRules = ['required', 'string', 'max:200'];

        if ($blogPost !== null) {
            $slugRules[] = Rule::unique('blog_posts', 'slug')->ignore($blogPost->id);
        }

        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => $slugRules,
            'category' => ['nullable', 'string', 'max:80'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'min:40'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'is_featured' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
    }

    private function uniqueSlug(string $value, ?BlogPost $blogPost = null): string
    {
        $base = Str::slug($value) ?: 'article';
        $slug = $base;
        $suffix = 2;

        $query = BlogPost::query()->where('slug', $slug);

        if ($blogPost !== null) {
            $query->where('id', '!=', $blogPost->id);
        }

        while ($query->exists()) {
            $slug = $base.'-'.$suffix++;
            $query = BlogPost::query()->where('slug', $slug);

            if ($blogPost !== null) {
                $query->where('id', '!=', $blogPost->id);
            }
        }

        return $slug;
    }
}
