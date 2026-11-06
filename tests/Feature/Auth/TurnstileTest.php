<?php

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();

    config()->set('services.turnstile.enabled', true);
    config()->set('services.turnstile.site_key', '1x00000000000000000000AA');
    config()->set('services.turnstile.secret_key', '1x0000000000000000000000000000000AA');
});

it('signs the user in once the challenge passes', function (): void {
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => true]),
    ]);

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'cf-turnstile-response' => 'token-from-the-widget',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);

    Http::assertSent(fn ($request): bool => $request->url() === config('services.turnstile.verify_url')
        && $request['secret'] === config('services.turnstile.secret_key')
        && $request['response'] === 'token-from-the-widget');
});

it('refuses a sign in that carries no token', function (): void {
    Http::fake();

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('cf-turnstile-response');

    $this->assertGuest();
    Http::assertNothingSent();
});

it('refuses a token the challenge rejected', function (): void {
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response([
            'success' => false,
            'error-codes' => ['invalid-input-response'],
        ]),
    ]);

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'cf-turnstile-response' => 'forged',
    ])->assertSessionHasErrors('cf-turnstile-response');

    $this->assertGuest();
});

it('refuses a sign in while the challenge cannot be reached', function (): void {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'cf-turnstile-response' => 'token-from-the-widget',
    ])->assertSessionHasErrors('cf-turnstile-response');

    $this->assertGuest();
});

it('keeps the plain sign in form when the challenge is off', function (): void {
    config()->set('services.turnstile.enabled', false);

    Http::fake();

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    Http::assertNothingSent();
});

it('hands the site key to the login screen', function (): void {
    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
        ->component('auth/login')
        ->where('turnstile.enabled', true)
        ->where('turnstile.site_key', '1x00000000000000000000AA'));
});

it('reports the challenge as off when no keys are configured', function (): void {
    config()->set('services.turnstile.site_key', null);

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
        ->component('auth/login')
        ->where('turnstile.enabled', false)
        ->where('turnstile.site_key', null));
});
