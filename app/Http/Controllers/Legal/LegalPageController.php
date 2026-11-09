<?php

namespace App\Http\Controllers\Legal;

use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use App\Services\Legal\LegalLibrary;
use Illuminate\Contracts\View\View;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public reading room for the platform's policies.
 *
 * Every page here is reachable without signing in: a prospective customer has to
 * be able to read the terms before they hand over a company name, and a
 * regulator has to be able to read them without an account at all. Only
 * documents with a published version are rendered, so a half-written draft can
 * never leak out of the admin screens.
 */
class LegalPageController extends Controller
{
    public function __construct(protected LegalLibrary $legal) {}

    /**
     * List the published documents.
     */
    public function index(): Response
    {
        $documents = $this->legal->documents()
            ->map(fn (LegalDocument $document): array => $this->summary($document))
            ->values()
            ->all();

        return Inertia::render('legal/index', [
            'documents' => $documents,
        ]);
    }

    /**
     * Read one document.
     */
    public function show(string $slug): Response
    {
        $document = $this->legal->document($slug);
        $version = $document === null ? null : $this->legal->currentVersion($document);

        abort_if($document === null || $version === null, 404);

        return Inertia::render('legal/show', [
            'document' => [
                ...$this->summary($document),
                'effective_on' => $version->effective_on?->toDateString(),
                'published_at' => $version->published_at?->toIso8601String(),
                'change_summary' => $version->change_summary,
                'html' => $version->html(),
            ],
            'others' => $this->legal->documents()
                ->reject(fn (LegalDocument $other): bool => $other->is($document))
                ->map(fn (LegalDocument $other): array => $this->summary($other))
                ->values()
                ->all(),
        ]);
    }

    /**
     * A print-ready copy of a published policy.
     *
     * The page is served as plain HTML rather than through Inertia so the file a
     * customer saves contains the document and nothing else: no navigation, no
     * account chrome. The reader's own "Save as PDF" then produces the copy of
     * record for the version that is live today.
     */
    public function print(string $slug): View
    {
        $document = $this->legal->document($slug);
        $version = $document === null ? null : $this->legal->currentVersion($document);

        abort_if($document === null || $version === null, 404);

        return view('legal.print', [
            'title' => $document->title,
            'summary' => $document->summary,
            'slug' => $document->slug,
            'version' => $version->label(),
            'effectiveOn' => $version->effective_on?->toFormattedDateString(),
            'publishedAt' => $version->published_at?->toFormattedDateString(),
            'body' => $version->html(),
            'platform' => (string) config('app.name'),
            'generatedAt' => now()->toFormattedDateString(),
        ]);
    }

    /**
     * The shape the two public pages render.
     *
     * @return array<string, mixed>
     */
    protected function summary(LegalDocument $document): array
    {
        $version = $document->publishedVersion ?? $this->legal->currentVersion($document);

        return [
            'slug' => $document->slug,
            'title' => $document->title,
            'summary' => $document->summary,
            'version' => $version?->label(),
            'effective_on' => $version?->effective_on?->toDateString(),
            'url' => $document->url(),
        ];
    }
}
