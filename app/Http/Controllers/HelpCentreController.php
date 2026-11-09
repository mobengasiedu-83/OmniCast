<?php

namespace App\Http\Controllers;

use App\Models\SupportArticle;
use App\Services\Support\SupportLibrary;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public help centre.
 *
 * It is the same library the signed-in support menu reads: an article written
 * once answers a visitor deciding whether to sign up and a client looking for the
 * same answer later.
 */
class HelpCentreController extends Controller
{
    public function __construct(protected SupportLibrary $library) {}

    /**
     * List the published articles, optionally narrowed by a search.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $articles = $this->library->articles($search);

        return Inertia::render('help/index', [
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

        return Inertia::render('help/show', [
            'article' => $this->library->article($article),
            'related' => $this->library->articles(null, 60),
            'contact' => $this->library->contact(),
        ]);
    }
}
