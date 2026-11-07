<?php

use App\Enums\ClientTheme;
use App\Enums\Role as RoleEnum;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

it('lists every colour theme with the organisation default', function (): void {
    [, , $user] = clientWithWallet();

    $this->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/appearance')
            ->has('themes', 6)
            ->where('themes.0.value', ClientTheme::Ember->value)
            ->where('themes.0.is_default', true)
            ->where('themes.1.value', ClientTheme::Lagoon->value)
            ->where('themes.1.is_default', false)
            ->where('currentTheme', ClientTheme::Ember->value)
            ->where('canChooseTheme', true));
});

it('stores the colour theme for the whole organisation and records who did it', function (): void {
    [$client, , $user] = clientWithWallet();

    $this->actingAs($user)
        ->put(route('appearance.update'), ['theme' => ClientTheme::Forest->value])
        ->assertRedirect()
        ->assertSessionHas('toast')
        ->assertSessionHasNoErrors();

    $client->refresh();

    expect($client->settings['theme'])->toBe(ClientTheme::Forest->value)
        ->and($client->theme())->toBe(ClientTheme::Forest)
        ->and(Activity::query()->where('description', 'updated organisation theme')->exists())->toBeTrue();
});

it('renders the chosen theme on the html element of every page', function (): void {
    [$client, , $user] = clientWithWallet();
    $client->update(['settings' => ['theme' => ClientTheme::Midnight->value]]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-theme="midnight"', false);
});

it('rejects a theme the platform does not ship', function (): void {
    [$client, , $user] = clientWithWallet();

    $this->actingAs($user)
        ->put(route('appearance.update'), ['theme' => 'neon'])
        ->assertSessionHasErrors('theme');

    expect($client->fresh()->settings['theme'] ?? null)->toBeNull();
});

it('only lets an organisation admin choose the theme', function (): void {
    [$client, , $user] = clientWithWallet();

    $operator = User::factory()->forClient($client)->create();
    $operator->syncRoles([RoleEnum::ClientOperator->value]);

    $this->actingAs($operator)
        ->put(route('appearance.update'), ['theme' => ClientTheme::Grape->value])
        ->assertForbidden();

    $this->actingAs($operator)
        ->get(route('appearance.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canChooseTheme', false));

    expect($client->fresh()->theme())->toBe(ClientTheme::Ember);

    $this->actingAs($user)
        ->put(route('appearance.update'), ['theme' => ClientTheme::Grape->value])
        ->assertRedirect();

    expect($client->fresh()->theme())->toBe(ClientTheme::Grape);
});

it('keeps platform users on the default theme', function (): void {
    $this->actingAs(platformUser())
        ->put(route('appearance.update'), ['theme' => ClientTheme::Sunset->value])
        ->assertForbidden();

    $this->actingAs(platformUser())
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentTheme', ClientTheme::Ember->value)
            ->where('canChooseTheme', false));
});

it('keeps the theme when the organisation settings are saved', function (): void {
    [$client, , $user] = clientWithWallet();
    $client->update(['settings' => ['theme' => ClientTheme::Lagoon->value]]);

    $this->actingAs($user)
        ->put(route('organisation-settings.update'), [
            'name' => 'Northwind Utilities',
            'email' => 'ops@northwind.test',
            'phone' => '+254700000000',
            'timezone' => 'Africa/Nairobi',
            'locale' => config('app.supported_locales', ['en'])[0],
        ])
        ->assertSessionHasNoErrors();

    expect($client->fresh()->theme())->toBe(ClientTheme::Lagoon);
});
