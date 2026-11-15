<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('blog/index', [
            'posts' => BlogPost::query()->published()->ordered()->get([
                'title', 'slug', 'category', 'excerpt', 'published_at', 'meta_title',
            ]),
        ]);
    }

    public function show(BlogPost $blogPost): Response
    {
        abort_unless($blogPost->status === 'published' && $blogPost->published_at !== null, 404);

        return Inertia::render('blog/show', [
            'post' => [
                'title' => $blogPost->title,
                'slug' => $blogPost->slug,
                'category' => $blogPost->category,
                'excerpt' => $blogPost->excerpt,
                'body' => $blogPost->html(),
                'meta_title' => $blogPost->meta_title,
                'meta_description' => $blogPost->meta_description,
                'published_at' => $blogPost->published_at->toDateString(),
            ],
        ]);
    }
}
