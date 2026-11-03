<?php

use App\Models\User;

it('creates a personal access token that is shown once', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('account.tokens.store'), [
            'name' => 'Reporting pipeline',
            'abilities' => ['read', 'write'],
        ])
        ->assertRedirect()
        ->assertSessionHas('new_api_token');

    $token = $user->tokens()->first();

    expect($user->tokens()->count())->toBe(1)
        ->and($token?->name)->toBe('Reporting pipeline')
        ->and($token?->abilities)->toBe(['read', 'write']);
});

it('revokes one of the signed-in users tokens', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Old integration', ['read']);

    $this->actingAs($user)
        ->delete(route('account.tokens.destroy', $token->accessToken->id))
        ->assertRedirect();

    expect($user->tokens()->count())->toBe(0);
});

it('does not revoke a token belonging to another user', function () {
    $user = User::factory()->create();
    $victim = User::factory()->create();
    $token = $victim->createToken('Victim integration', ['read']);

    $this->actingAs($user)
        ->delete(route('account.tokens.destroy', $token->accessToken->id))
        ->assertRedirect();

    expect($victim->tokens()->count())->toBe(1);
});

it('authenticates api requests with a personal access token', function () {
    $user = User::factory()->create();
    $plainTextToken = $user->createToken('api', ['read'])->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
        ->getJson(route('api.v1.me'))
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);
});

it('rejects api requests without a token', function () {
    $this->getJson(route('api.v1.me'))->assertUnauthorized();
});
