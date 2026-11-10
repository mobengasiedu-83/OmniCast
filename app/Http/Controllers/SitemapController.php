<?php

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use App\Models\SupportArticle;
use App\Services\Legal\LegalLibrary;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * List crawlable public pages and published content.
     */
    public function __invoke(LegalLibrary $legal): Response
    {
        $urls = collect([
            route('home'),
            route('pricing'),
            route('user-manual'),
            route('api-docs'),
            route('contact.show'),
            route('help.index'),
            route('legal.index'),
        ])->map(fn (string $url): array => ['loc' => $url])
            ->all();

        $urls = [
            ...$urls,
            ...SupportArticle::query()
                ->published()
                ->orderBy('id')
                ->get(['slug', 'published_at', 'updated_at'])
                ->map(fn (SupportArticle $article): array => [
                    'loc' => route('help.show', $article->slug),
                    'lastmod' => ($article->updated_at ?? $article->published_at)?->toAtomString(),
                ])
                ->all(),
            ...$legal->documents()
                ->map(fn (LegalDocument $document): array => [
                    'loc' => $document->url(),
                    'lastmod' => ($document->publishedVersion?->updated_at
                        ?? $document->publishedVersion?->published_at)?->toAtomString(),
                ])
                ->all(),
        ];

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
