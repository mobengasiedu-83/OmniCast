<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Personal access tokens for the platform HTTP API.
 */
class ApiTokenController extends Controller
{
    /**
     * The abilities a token may be granted.
     *
     * @var list<string>
     */
    public const ABILITIES = ['read', 'write'];

    /**
     * List the signed-in user's tokens.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $newToken = $request->session()->get('new_api_token');

        return Inertia::render('settings/api-tokens', [
            'tokens' => $user->tokens()
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (PersonalAccessToken $token): array => [
                    'id' => $token->id,
                    'name' => $token->name,
                    'abilities' => $token->abilities,
                    'last_used_at' => $token->last_used_at?->toIso8601String(),
                    'expires_at' => $token->expires_at?->toIso8601String(),
                    'created_at' => $token->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'abilities' => self::ABILITIES,
            'newToken' => is_string($newToken) ? $newToken : null,
        ]);
    }

    /**
     * Mint a new personal access token.
     */
    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'abilities' => ['array'],
            'abilities.*' => ['string', Rule::in(self::ABILITIES)],
        ]);

        /** @var list<string> $abilities */
        $abilities = is_array($validated['abilities'] ?? null) && $validated['abilities'] !== []
            ? array_values(array_filter($validated['abilities'], 'is_string'))
            : ['read'];

        $token = $user->createToken($validated['name'], $abilities);

        return back()
            ->with('new_api_token', $token->plainTextToken)
            ->with('toast', [
                'type' => 'success',
                'message' => 'Token created. Copy it now, it will not be shown again.',
            ]);
    }

    /**
     * Revoke one of the signed-in user's tokens.
     */
    public function destroy(Request $request, int $token): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $revoked = $user->tokens()->whereKey($token)->delete() > 0;

        return back()->with('toast', [
            'type' => $revoked ? 'success' : 'error',
            'message' => $revoked ? 'Token revoked.' : 'That token no longer exists.',
        ]);
    }
}
