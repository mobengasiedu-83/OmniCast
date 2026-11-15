<?php

use App\Enums\Role as RoleEnum;
use App\Models\BlogPost;
use Database\Seeders\BlogPostSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

it('lists published posts publicly and keeps drafts private', function (): void {
    BlogPost::query()->create([
        'title' => 'Public guide', 'slug' => 'public-guide', 'body' => '# A public guide body.',
        'status' => 'published', 'published_at' => now(),
    ]);
    BlogPost::query()->create([
        'title' => 'Unpublished notes', 'slug' => 'unpublished-notes', 'body' => 'Private draft body.',
        'status' => 'draft',
    ]);

    $this->get(route('blog.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('blog/index')
            ->has('posts', 1)
            ->where('posts.0.slug', 'public-guide'));

    $this->get(route('blog.show', 'unpublished-notes'))->assertNotFound();
});

it('renders published article content safely to public visitors', function (): void {
    $post = BlogPost::query()->create([
        'title' => 'Public guide', 'slug' => 'public-guide',
        'body' => "# Helpful guide\n\n<script>alert('unsafe')</script>\n\n[Pricing](/pricing)",
        'meta_title' => 'Helpful guide SEO title', 'meta_description' => 'A practical guide.',
        'status' => 'published', 'published_at' => now(),
    ]);

    $this->get(route('blog.show', $post->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('blog/show')
            ->where('post.title', 'Public guide')
            ->where('post.body', fn (string $body): bool => str_contains($body, '<h1>Helpful guide</h1>')
                && ! str_contains($body, '<script>')));
});

it('allows a permitted platform editor to create, update and delete posts', function (): void {
    $editor = platformUser();
    $payload = [
        'title' => 'A new post', 'slug' => 'a-new-post', 'category' => 'Guides',
        'excerpt' => 'A useful summary.', 'body' => 'A sufficiently detailed article body for validation.',
        'meta_title' => 'A new post | Omnicast', 'meta_description' => 'Read the new post.',
        'status' => 'draft', 'is_featured' => false, 'sort_order' => 0,
    ];

    $this->actingAs($editor)->post(route('admin.blog.store'), $payload)->assertRedirect();
    $post = BlogPost::query()->where('slug', 'a-new-post')->firstOrFail();
    expect($post->status)->toBe('draft')->and($post->published_at)->toBeNull();

    $this->actingAs($editor)->put(route('admin.blog.update', $post), [
        ...$payload,
        'title' => 'Updated post', 'slug' => 'updated-post', 'status' => 'published',
    ])->assertRedirect();
    $post->refresh();
    expect($post->title)->toBe('Updated post')
        ->and($post->status)->toBe('published')
        ->and($post->published_at)->not->toBeNull();

    $this->actingAs($editor)->delete(route('admin.blog.destroy', $post))->assertRedirect();
    $this->assertModelMissing($post);
});

it('restricts blog management to platform users with the blog permission', function (): void {
    $post = BlogPost::query()->create([
        'title' => 'A post', 'slug' => 'a-post', 'body' => 'A sufficiently detailed article body for validation.',
        'status' => 'draft',
    ]);

    $this->get(route('admin.blog.index'))->assertRedirect(route('login'));
    $this->actingAs(platformUser(RoleEnum::FinanceOfficer))
        ->get(route('admin.blog.index'))
        ->assertForbidden();
    $this->actingAs(platformUser())
        ->get(route('admin.blog.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/blog/index')->has('posts', 1));

    expect($post->fresh())->not->toBeNull();
});

it('seeds the supplied articles once without overwriting later edits', function (): void {
    $seeder = app(BlogPostSeeder::class);
    $seeder->run();

    $post = BlogPost::query()->where('slug', 'sms-api-integration-ghana-otp-automated-alerts')->firstOrFail();
    $post->update(['title' => 'An editor’s improved title']);
    $seeder->run();

    expect(BlogPost::query()->count())->toBe(5)
        ->and($post->fresh()->title)->toBe('An editor’s improved title');
});
