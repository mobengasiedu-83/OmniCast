<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

beforeEach(function (): void {
    $this->withoutVite();
});

test('security page is displayed', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);
    Features::passkeys([
        'confirmPassword' => true,
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/security')
            ->where('canManagePasskeys', true)
            ->where('passkeys', [])
            ->where('canManageTwoFactor', true)
            ->where('twoFactorEnabled', false),
        );
});

test('security page requires password confirmation when enabled', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    $user = User::factory()->create();

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $response = $this->actingAs($user)
        ->get(route('security.edit'));

    $response->assertRedirect(route('password.confirm'));
});

test('security page renders without two factor when feature is disabled', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    config(['fortify.features' => []]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/security')
            ->where('canManagePasskeys', false)
            ->where('passkeys', [])
            ->where('canManageTwoFactor', false)
            ->missing('twoFactorEnabled')
            ->missing('requiresConfirmation'),
        );
});

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('security.edit'));
});

test('users can regenerate two factor recovery codes', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();
    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()]);

    $oldCodes = $this->getJson(route('two-factor.recovery-codes'))
        ->assertOk()
        ->json();

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->from(route('security.edit'))
        ->post(route('two-factor.regenerate-recovery-codes'))
        ->assertRedirect(route('security.edit'))
        ->assertSessionHas('status', Fortify::RECOVERY_CODES_GENERATED);

    $newCodes = $this->getJson(route('two-factor.recovery-codes'))
        ->assertOk()
        ->json();

    expect($newCodes)->toBeArray()->toHaveCount(8);
    expect($newCodes)->not->toBe($oldCodes);
});

test('users can remove their own passkeys', function () {
    $this->skipUnlessFortifyHas(Features::passkeys());
    Features::passkeys(['confirmPassword' => true]);

    $user = User::factory()->create();
    $passkey = $user->passkeys()->create([
        'name' => 'Work laptop',
        'credential_id' => 'credential-'.$user->id,
        'credential' => ['aaguid' => 'unknown'],
    ]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->from(route('security.edit'))
        ->delete(route('passkey.destroy', $passkey))
        ->assertRedirect(route('security.edit'))
        ->assertSessionHas('status', 'passkey-deleted');

    $this->assertDatabaseMissing('passkeys', ['id' => $passkey->id]);
});

test('users cannot remove another account passkeys', function () {
    $this->skipUnlessFortifyHas(Features::passkeys());
    Features::passkeys(['confirmPassword' => true]);

    $owner = User::factory()->create();
    $passkey = $owner->passkeys()->create([
        'name' => 'Work laptop',
        'credential_id' => 'credential-'.$owner->id,
        'credential' => ['aaguid' => 'unknown'],
    ]);
    $otherUser = User::factory()->create();

    $this->actingAs($otherUser)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('passkey.destroy', $passkey))
        ->assertForbidden();

    $this->assertModelExists($passkey);
});
