<?php

use App\Enums\LegalDocumentType;
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
        route('user-manual'),
        route('api-docs'),
        route('contact.show'),
        route('help.index'),
        route('legal.index'),
    )->not->toContain(route('dashboard'));
});

it('includes only published help and legal content', function (): void {
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
        route('help.show', $article->slug),
        route('legal.show', LegalDocumentType::Terms->value),
    )->not->toContain(route('help.show', $draft->slug));
});
