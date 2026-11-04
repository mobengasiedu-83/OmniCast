<?php

use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ClientDocumentController;
use App\Http\Controllers\Admin\ClientEntitlementController;
use App\Http\Controllers\Admin\ClientRegistrationController;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\LoginAuditController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RateCardController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Platform administration
|--------------------------------------------------------------------------
|
| Routes are limited to platform users and then checked against the permission
| for the specific administrative area. Client users are rejected by the
| `platform` middleware even if a role is later granted a platform permission.
|
*/

Route::middleware(['auth', 'verified', 'platform'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::middleware('can:users.manage')->group(function (): void {
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::post('users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
            Route::post('users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');

            Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index');
            Route::post('invitations', [InvitationController::class, 'store'])
                ->middleware('throttle:invitations')
                ->name('invitations.store');
            Route::post('invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('invitations.resend');
            Route::post('invitations/{invitation}/revoke', [InvitationController::class, 'revoke'])->name('invitations.revoke');
            Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');

            Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
            Route::put('roles/{role:name}', [RoleController::class, 'update'])->name('roles.update');

            Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');

            Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');
            Route::delete('sessions/{user}', [SessionController::class, 'destroy'])->name('sessions.destroy');
            Route::delete('sessions/{user}/{session}', [SessionController::class, 'destroyOne'])->name('sessions.destroy-one');

            Route::get('login-audit', [LoginAuditController::class, 'index'])->name('login-audit.index');

            Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
            Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
            Route::get('settings/plans', [SettingsController::class, 'plans'])->name('settings.plans');
            Route::put('settings/plans/{client}', [SettingsController::class, 'assignPlan'])->name('settings.plans.assign');
            Route::get('resource-requests', [SettingsController::class, 'requests'])->name('resource-requests.index');
            Route::put('resource-requests/{resourceRequest}', [SettingsController::class, 'reviewRequest'])->name('resource-requests.review');
        });

        Route::middleware('can:clients.manage')->group(function (): void {
            Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
            Route::get('clients/{client}', [ClientController::class, 'show'])->name('clients.show');
            Route::put('clients/{client}', [ClientController::class, 'update'])->name('clients.update');
            Route::post('clients/{client}/activate', [ClientController::class, 'activate'])->name('clients.activate');
            Route::post('clients/{client}/suspend', [ClientController::class, 'suspend'])->name('clients.suspend');
            Route::post('clients/{client}/reactivate', [ClientController::class, 'reactivate'])->name('clients.reactivate');
            Route::post('clients/{client}/archive', [ClientController::class, 'archive'])->name('clients.archive');

            Route::post('clients/{client}/documents', [ClientDocumentController::class, 'store'])
                ->name('clients.documents.store');
            Route::delete('clients/{client}/documents/{document}', [ClientDocumentController::class, 'destroy'])
                ->name('clients.documents.destroy');

            Route::put('clients/{client}/entitlements', [ClientEntitlementController::class, 'update'])
                ->name('clients.entitlements.update');
        });

        Route::middleware('can:wallet.manage')->group(function (): void {
            Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
            Route::post('payments/{payment}/verify', [PaymentController::class, 'verify'])->name('payments.verify');

            Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        });

        Route::middleware('can:rates.view')->group(function (): void {
            Route::get('rate-cards', [RateCardController::class, 'index'])->name('rate-cards.index');
        });

        Route::middleware('can:rates.manage')->group(function (): void {
            Route::put('rates/{rate}', [RateCardController::class, 'updateRate'])->name('rates.update');
        });

        Route::middleware('can:clients.approve')->group(function (): void {
            Route::get('client-registrations', [ClientRegistrationController::class, 'index'])
                ->name('client-registrations.index');
            Route::post('client-registrations/{registration}/approve', [ClientRegistrationController::class, 'approve'])
                ->name('client-registrations.approve');
            Route::post('client-registrations/{registration}/reject', [ClientRegistrationController::class, 'reject'])
                ->name('client-registrations.reject');

            Route::post('clients/{client}/documents/{document}/review', [ClientDocumentController::class, 'review'])
                ->name('clients.documents.review');
            Route::get('clients/{client}/documents/{document}/view', [ClientDocumentController::class, 'view'])
                ->name('clients.documents.view');
        });
    });
