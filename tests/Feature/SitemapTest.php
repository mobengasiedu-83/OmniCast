<?php

use App\Enums\LegalDocumentType;
use App\Models\BlogPost;
use App\Models\SupportArticle;

it('serves a valid sitemap of public static pages', function (): void {
    $response = $this->get(route('sitemap'));

    $response->assertOk()
        ->assertHeader('content-type', 'application/xml; charset=UTF-8');

    $sitemap = simplexml_load_string($response->getContent(), SimpleXMLElement::class, LIBXML_NONET);

    expect($sitemap)->toBeInstanceOf(SimpleXMLElement::class);

    $namespace = 'http://www.sitemaps.org/schemas/sitemap/0.9';
    $sitemap->registerXPathNamespace('sm', $namespace);
    $locations = collect($sitemap->xpath('//sm:url/sm:loc'))
        ->map(fn (SimpleXMLElement $location): string => (string) $location);

    expect($locations)->toContain(
        route('home'),
        route('pricing'),
        route('contact.show'),
        route('blog.index'),
        route('help.index'),
        route('legal.index'),
    )->not->toContain(route('dashboard'), route('user-manual'), route('api-docs'));
});

it('includes only published help and legal content', function (): void {
    $blogPost = BlogPost::query()->create([
        'title' => 'Public blog post', 'slug' => 'public-blog-post', 'body' => 'A public article body.',
        'status' => 'published', 'published_at' => now(),
    ]);
    $draftBlogPost = BlogPost::query()->create([
        'title' => 'Draft blog post', 'slug' => 'draft-blog-post', 'body' => 'A draft article body.',
        'status' => 'draft',
    ]);
    $article = SupportArticle::factory()->published()->create(['slug' => 'public-guide']);
    $draft = SupportArticle::factory()->create(['slug' => 'private-draft']);
    legalDocument(LegalDocumentType::Terms);

    $response = $this->get(route('sitemap'));

    $response->assertOk();

    $sitemap = simplexml_load_string($response->getContent(), SimpleXMLElement::class, LIBXML_NONET);
    $namespace = 'http://www.sitemaps.org/schemas/sitemap/0.9';
    $sitemap->registerXPathNamespace('sm', $namespace);

    $locations = collect($sitemap->xpath('//sm:url/sm:loc'))
        ->map(fn (SimpleXMLElement $location): string => (string) $location);

    expect($locations)->toContain(
        route('blog.show', $blogPost->slug),
        route('help.show', $article->slug),
        route('legal.show', LegalDocumentType::Terms->value),
    )->not->toContain(route('blog.show', $draftBlogPost->slug), route('help.show', $draft->slug));
});
