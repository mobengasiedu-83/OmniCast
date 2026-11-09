<?php

use App\Http\Controllers\Settings\ApiTokenController;
use App\Http\Controllers\Settings\LegalDocumentController;
use App\Http\Controllers\Settings\NotificationPreferenceController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SessionController;
use App\Http\Controllers\Settings\TeamController;
use App\Http\Controllers\Settings\ThemeController;
use App\Http\Controllers\Settings\WebhookEndpointController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', [ThemeController::class, 'edit'])->name('appearance.edit');
    Route::put('settings/theme', [ThemeController::class, 'update'])->name('appearance.update');

    Route::get('settings/sessions', [SessionController::class, 'index'])->name('account.sessions.index');
    Route::delete('settings/sessions', [SessionController::class, 'destroyOthers'])->name('account.sessions.destroy-others');
    Route::delete('settings/sessions/{session}', [SessionController::class, 'destroy'])->name('account.sessions.destroy');

    Route::get('settings/api-tokens', [ApiTokenController::class, 'index'])->name('account.tokens.index');
    Route::post('settings/api-tokens', [ApiTokenController::class, 'store'])->name('account.tokens.store');
    Route::delete('settings/api-tokens/{token}', [ApiTokenController::class, 'destroy'])->name('account.tokens.destroy');

    Route::get('settings/notifications', [NotificationPreferenceController::class, 'edit'])
        ->name('notifications.preferences.edit');
    Route::put('settings/notifications', [NotificationPreferenceController::class, 'update'])
        ->name('notifications.preferences.update');

    Route::get('settings/legal', [LegalDocumentController::class, 'index'])
        ->name('legal.settings.index');
    Route::post('settings/legal/accept', [LegalDocumentController::class, 'accept'])
        ->name('legal.settings.accept');
    Route::post('settings/legal/dpa', [LegalDocumentController::class, 'requestDpa'])
        ->name('legal.settings.dpa');

    Route::get('settings/team', [TeamController::class, 'index'])->name('team.index');
    Route::post('settings/team', [TeamController::class, 'store'])
        ->middleware('throttle:invitations')
        ->name('team.store');
    Route::post('settings/team/{invitation}/resend', [TeamController::class, 'resend'])->name('team.resend');
    Route::delete('settings/team/{invitation}', [TeamController::class, 'destroy'])->name('team.destroy');

    Route::get('settings/webhooks', [WebhookEndpointController::class, 'index'])->name('webhooks.index');
    Route::post('settings/webhooks', [WebhookEndpointController::class, 'store'])->name('webhooks.store');
    Route::put('settings/webhooks/{endpoint}', [WebhookEndpointController::class, 'update'])->name('webhooks.update');
    Route::patch('settings/webhooks/{endpoint}/toggle', [WebhookEndpointController::class, 'toggle'])->name('webhooks.toggle');
    Route::patch('settings/webhooks/{endpoint}/rotate', [WebhookEndpointController::class, 'rotate'])->name('webhooks.rotate');
    Route::post('settings/webhooks/{endpoint}/test', [WebhookEndpointController::class, 'test'])->name('webhooks.test');
    Route::delete('settings/webhooks/{endpoint}', [WebhookEndpointController::class, 'destroy'])->name('webhooks.destroy');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
