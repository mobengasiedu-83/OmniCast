<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesTenantClient;
use App\Http\Requests\Template\TemplateCategoryRequest;
use App\Models\TemplateCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The tenant's own template categories, shown next to the platform ones.
 */
class TemplateCategoryController extends Controller
{
    use ResolvesTenantClient;

    /**
     * List every category the account can file a template under.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', TemplateCategory::class);

        return Inertia::render('templates/categories', [
            'categories' => $this->categories($request),
            'can' => [
                'create' => Gate::allows('create', TemplateCategory::class),
            ],
        ]);
    }

    /**
     * Add a category to the tenant's library.
     */
    public function store(TemplateCategoryRequest $request): RedirectResponse
    {
        Gate::authorize('create', TemplateCategory::class);

        $category = TemplateCategory::query()->create($request->categoryAttributes());

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Category \"{$category->name}\" was added to the library.",
        ]);
    }

    /**
     * Rename or describe an existing category.
     */
    public function update(TemplateCategoryRequest $request, TemplateCategory $category): RedirectResponse
    {
        Gate::authorize('update', $category);

        $category->update($request->categoryAttributes());

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Category \"{$category->name}\" was updated.",
        ]);
    }

    /**
     * Remove a category; its templates stay in the library, unfiled.
     */
    public function destroy(TemplateCategory $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        $name = $category->name;
        $category->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Category \"{$name}\" was removed. Its templates stay in the library.",
        ]);
    }

    /**
     * The categories in library order, with how many templates each holds.
     *
     * @return list<array<string, mixed>>
     */
    protected function categories(Request $request): array
    {
        return TemplateCategory::query()
            ->visibleTo($request->user()->client_id)
            ->withCount('templates')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (TemplateCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'sort_order' => (int) $category->sort_order,
                'is_system' => $category->isSystem(),
                'templates_count' => (int) $category->getAttribute('templates_count'),
                'can' => [
                    'update' => Gate::allows('update', $category),
                    'delete' => Gate::allows('delete', $category),
                ],
            ])
            ->all();
    }
}
