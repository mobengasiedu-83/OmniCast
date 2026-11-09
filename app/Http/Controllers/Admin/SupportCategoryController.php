<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SupportCategoryAudience;
use App\Enums\SupportPriority;
use App\Http\Controllers\Controller;
use App\Models\SupportCategory;
use App\Models\SupportTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The categories both forms offer, and the team each one routes to.
 *
 * A category is configuration, not code: adding "Wallet top-up" here makes it
 * appear on the public contact form and the client ticket form, with the priority
 * and team it names. Once conversations are filed under a category it can be
 * deactivated but not deleted, so history never loses its reason.
 */
class SupportCategoryController extends Controller
{
    /**
     * List the categories and their usage.
     */
    public function index(): Response
    {
        $categories = SupportCategory::query()
            ->with('team:id,name')
            ->withCount('tickets')
            ->ordered()
            ->get()
            ->map(fn (SupportCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'audience' => $category->audience->value,
                'audience_label' => $category->audience->label(),
                'team_id' => $category->support_team_id,
                'team' => $category->team?->name,
                'default_priority' => $category->default_priority->value,
                'default_priority_label' => $category->default_priority->label(),
                'is_active' => $category->is_active,
                'sort_order' => $category->sort_order,
                'tickets_count' => $category->tickets_count,
            ]);

        return Inertia::render('admin/support/categories', [
            'categories' => $categories,
            'teams' => SupportTeam::query()->ordered()->get(['id', 'name'])->map(fn (SupportTeam $team): array => [
                'value' => (string) $team->id,
                'label' => $team->name,
            ])->all(),
            'audiences' => SupportCategoryAudience::catalogue(),
            'priorities' => SupportPriority::catalogue(),
        ]);
    }

    /**
     * Add a category.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCategory($request);

        SupportCategory::query()->create([
            ...$validated,
            'slug' => $this->slug($validated['name']),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Category added.']);
    }

    /**
     * Save a category.
     */
    public function update(Request $request, SupportCategory $category): RedirectResponse
    {
        $validated = $this->validateCategory($request);

        $category->forceFill($validated)->save();

        return back()->with('toast', ['type' => 'success', 'message' => 'Category saved.']);
    }

    /**
     * Remove a category nothing has been filed under.
     */
    public function destroy(SupportCategory $category): RedirectResponse
    {
        if ($category->tickets()->exists()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'This category has conversations against it. Turn it off instead of deleting it, so the history keeps its reason.',
            ]);
        }

        $category->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Category removed.']);
    }

    /**
     * The rules shared by adding and saving.
     *
     * @return array<string, mixed>
     */
    protected function validateCategory(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'audience' => ['required', Rule::enum(SupportCategoryAudience::class)],
            'support_team_id' => ['nullable', 'integer', Rule::exists('support_teams', 'id')],
            'default_priority' => ['required', Rule::enum(SupportPriority::class)],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'name.required' => 'Give the category the name a visitor should see.',
        ]);
    }

    /**
     * A slug that is stable and unique, derived from the name.
     */
    protected function slug(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $suffix = 2;

        while (SupportCategory::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
