<?php

use App\Http\Controllers\Auth\InvitationAcceptanceController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ClientRegistrationController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SenderIdController;
use App\Http\Controllers\Settings\ClientSettingsController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware('guest')->group(function (): void {
    Route::redirect('register', 'register-client')->name('register');

    Route::get('register-client', [ClientRegistrationController::class, 'create'])
        ->name('client-registration.create');

    Route::post('register-client', [ClientRegistrationController::class, 'store'])
        ->middleware('throttle:client-registrations')
        ->name('client-registration.store');

    Route::post('register-client/verify', [ClientRegistrationController::class, 'verify'])
        ->middleware('throttle:client-registrations')
        ->name('client-registration.verify');

    Route::post('register-client/password', [ClientRegistrationController::class, 'setPassword'])
        ->middleware('throttle:client-registrations')
        ->name('client-registration.password');

    Route::get('invitations/{token}', [InvitationAcceptanceController::class, 'show'])
        ->name('invitations.accept.show');

    Route::post('invitations/{token}', [InvitationAcceptanceController::class, 'store'])
        ->middleware('throttle:invitations')
        ->name('invitations.accept.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('settings/organisation', [ClientSettingsController::class, 'edit'])->name('organisation-settings.edit');
    Route::put('settings/organisation', [ClientSettingsController::class, 'update'])->name('organisation-settings.update');
    Route::post('settings/organisation/documents', [ClientSettingsController::class, 'storeDocument'])->name('organisation-settings.documents.store');
    Route::get('settings/resource-requests', [ClientSettingsController::class, 'requests'])->name('organisation-settings.requests');
    Route::post('settings/resource-requests', [ClientSettingsController::class, 'storeRequest'])->name('organisation-settings.requests.store');
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::get('campaigns/create', [CampaignController::class, 'create'])->name('campaigns.create');
    Route::post('campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
    Route::get('campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
    Route::get('campaigns/{campaign}/edit', [CampaignController::class, 'edit'])->name('campaigns.edit');
    Route::put('campaigns/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
    Route::delete('campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');
    Route::post('campaigns/{campaign}/submit', [CampaignController::class, 'submit'])->name('campaigns.submit');
    Route::post('campaigns/{campaign}/approve', [CampaignController::class, 'approve'])->name('campaigns.approve');
    Route::post('campaigns/{campaign}/reject', [CampaignController::class, 'reject'])->name('campaigns.reject');
    Route::post('campaigns/{campaign}/launch', [CampaignController::class, 'launch'])->name('campaigns.launch');
    Route::post('campaigns/{campaign}/cancel', [CampaignController::class, 'cancel'])->name('campaigns.cancel');

    Route::get('contacts/import', [ContactController::class, 'showImport'])->name('contacts.import.create');
    Route::post('contacts/import', [ContactController::class, 'import'])->name('contacts.import');
    Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::get('contacts/create', [ContactController::class, 'create'])->name('contacts.create');
    Route::post('contacts', [ContactController::class, 'store'])->name('contacts.store');
    Route::get('contacts/{contact}/edit', [ContactController::class, 'edit'])->name('contacts.edit');
    Route::put('contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
    Route::delete('contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');

    Route::get('sender-ids', [SenderIdController::class, 'index'])->name('sender-ids.index');
    Route::post('sender-ids', [SenderIdController::class, 'store'])->name('sender-ids.store');
    Route::post('sender-ids/{senderId}/approve', [SenderIdController::class, 'approve'])->name('sender-ids.approve');
    Route::post('sender-ids/{senderId}/reject', [SenderIdController::class, 'reject'])->name('sender-ids.reject');
    Route::delete('sender-ids/{senderId}', [SenderIdController::class, 'destroy'])->name('sender-ids.destroy');

    Route::get('wallet', [WalletController::class, 'index'])->name('wallet.index');
    Route::post('wallet/top-up', [WalletController::class, 'topUp'])->name('wallet.top-up');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
