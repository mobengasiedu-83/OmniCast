<?php

namespace App\Http\Controllers\Settings;

use App\Enums\ClientTheme;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ThemeController extends Controller
{
    /**
     * Show the appearance screen: light/dark for the individual, colour theme
     * for the whole organisation.
     */
    public function edit(Request $request): Response
    {
        $client = $request->user()?->client;

        return Inertia::render('settings/appearance', [
            'themes' => ClientTheme::options(),
            'currentTheme' => ($client?->theme() ?? ClientTheme::default())->value,
            'canChooseTheme' => $client !== null && Gate::check('users.manage'),
        ]);
    }

    /**
     * Store the organisation's colour theme. Every user in the tenant picks it
     * up on their next page load.
     */
    public function update(Request $request): RedirectResponse
    {
        $client = $request->user()?->client;
        abort_unless($client !== null, 403);
        Gate::authorize('users.manage');

        $validated = $request->validate([
            'theme' => ['required', 'string', Rule::enum(ClientTheme::class)],
        ]);

        $theme = ClientTheme::resolve($validated['theme']);

        $client->settings = [...($client->settings ?? []), 'theme' => $theme->value];
        $client->save();

        activity()->causedBy($request->user())->performedOn($client)
            ->withProperties(['theme' => $theme->value])->log('updated organisation theme');

        return back()->with('toast', ['type' => 'success', 'message' => "Theme set to {$theme->label()}."]);
    }
}
