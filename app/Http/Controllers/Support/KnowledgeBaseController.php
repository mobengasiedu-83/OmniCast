<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportArticle;
use App\Services\Support\SupportLibrary;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The help centre inside the signed-in application.
 *
 * The articles are the public library, read under the app chrome: a client looking
 * for an answer should not have to leave OmniCast and lose the campaign they were
 * working on.
 */
class KnowledgeBaseController extends Controller
{
    public function __construct(protected SupportLibrary $library) {}

    /**
     * List the published articles.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $articles = $this->library->articles($search);

        return Inertia::render('support/knowledge-base/index', [
            'articles' => $articles,
            'categories' => array_values(array_unique(array_filter(array_column($articles, 'category')))),
            'filters' => ['search' => $search],
            'contact' => $this->library->contact(),
        ]);
    }

    /**
     * Read one published article.
     */
    public function show(SupportArticle $article): Response
    {
        abort_unless($article->is_published, 404);

        $article->increment('views');

        return Inertia::render('support/knowledge-base/show', [
            'article' => $this->library->article($article),
            'articles' => $this->library->articles(null, 60),
            'contact' => $this->library->contact(),
        ]);
    }
}
