<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The teams conversations are routed to.
 *
 * A team is an inbox and a name, not a group of accounts: the platform is small
 * enough that whoever is on shift answers, and the address decides who is told.
 * A team with history behind it can be deactivated but not deleted.
 */
class SupportTeamController extends Controller
{
    /**
     * List the teams and their usage.
     */
    public function index(): Response
    {
        $teams = SupportTeam::query()
            ->withCount(['categories', 'tickets'])
            ->ordered()
            ->get()
            ->map(fn (SupportTeam $team): array => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'email' => $team->email,
                'description' => $team->description,
                'is_active' => $team->is_active,
                'sort_order' => $team->sort_order,
                'categories_count' => $team->categories_count,
                'tickets_count' => $team->tickets_count,
            ]);

        return Inertia::render('admin/support/teams', ['teams' => $teams]);
    }

    /**
     * Add a team.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateTeam($request);

        SupportTeam::query()->create([
            ...$validated,
            'slug' => $this->slug($validated['name']),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Team added.']);
    }

    /**
     * Save a team.
     */
    public function update(Request $request, SupportTeam $team): RedirectResponse
    {
        $team->forceFill($this->validateTeam($request))->save();

        return back()->with('toast', ['type' => 'success', 'message' => 'Team saved.']);
    }

    /**
     * Remove a team nothing is routed to.
     */
    public function destroy(SupportTeam $team): RedirectResponse
    {
        if ($team->tickets()->exists() || $team->categories()->exists()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'This team still owns categories or conversations. Move them first, or turn the team off.',
            ]);
        }

        $team->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Team removed.']);
    }

    /**
     * The rules shared by adding and saving.
     *
     * @return array<string, mixed>
     */
    protected function validateTeam(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['nullable', 'string', 'email', 'max:160'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'name.required' => 'Give the team a name.',
            'email.email' => 'The routing address must be a valid email address.',
        ]);
    }

    /**
     * A slug that is stable and unique, derived from the name.
     */
    protected function slug(string $name): string
    {
        $base = Str::slug($name) ?: 'team';
        $slug = $base;
        $suffix = 2;

        while (SupportTeam::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
