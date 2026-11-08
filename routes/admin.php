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
use App\Http\Controllers\Admin\QueueController;
use App\Http\Controllers\Admin\RateCardController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SmppController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WebhookController;
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

        Route::middleware('can:queues.view')->group(function (): void {
            Route::get('queues', [QueueController::class, 'index'])->name('queues.index');
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

        Route::middleware('can:reports.view')->group(function (): void {
            Route::get('reports', [AdminReportController::class, 'index'])->name('reports.index');
            Route::get('reports/clients', [AdminReportController::class, 'clients'])->name('reports.clients');
            Route::get('reports/voice', [AdminReportController::class, 'voice'])->name('reports.voice');
            Route::get('reports/sms', [AdminReportController::class, 'sms'])->name('reports.sms');
            Route::get('reports/email', [AdminReportController::class, 'email'])->name('reports.email');
            Route::get('reports/revenue', [AdminReportController::class, 'revenue'])->name('reports.revenue');
            Route::get('reports/providers', [AdminReportController::class, 'providers'])->name('reports.providers');
            Route::get('reports/exports', [AdminReportController::class, 'exportHistory'])->name('reports.exports.index');
            Route::get('reports/exports/{export}/download', [AdminReportController::class, 'downloadExport'])
                ->name('reports.exports.download');
            Route::post('reports/exports', [AdminReportController::class, 'storeExport'])
                ->middleware('can:reports.export')
                ->name('reports.exports.store');
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

        Route::middleware('can:webhooks.view')->group(function (): void {
            Route::get('webhooks', [WebhookController::class, 'index'])->name('webhooks.index');
        });

        Route::middleware('can:webhooks.manage')->group(function (): void {
            Route::post('webhooks', [WebhookController::class, 'store'])->name('webhooks.store');
            Route::put('webhooks/{endpoint}', [WebhookController::class, 'update'])->name('webhooks.update');
            Route::patch('webhooks/{endpoint}/toggle', [WebhookController::class, 'toggle'])->name('webhooks.toggle');
            Route::patch('webhooks/{endpoint}/rotate', [WebhookController::class, 'rotate'])->name('webhooks.rotate');
            Route::post('webhooks/{endpoint}/test', [WebhookController::class, 'test'])->name('webhooks.test');
            Route::post('webhook-deliveries/{delivery}/replay', [WebhookController::class, 'replay'])
                ->name('webhook-deliveries.replay');
            Route::delete('webhooks/{endpoint}', [WebhookController::class, 'destroy'])->name('webhooks.destroy');
        });

        Route::middleware('can:smpp.view')->group(function (): void {
            Route::get('smpp', [SmppController::class, 'index'])->name('smpp.index');
        });

        Route::middleware('can:smpp.manage')->group(function (): void {
            Route::post('smpp', [SmppController::class, 'store'])->name('smpp.store');
            Route::put('smpp/{account}', [SmppController::class, 'update'])->name('smpp.update');
            Route::patch('smpp/{account}/toggle', [SmppController::class, 'toggle'])->name('smpp.toggle');
            Route::patch('smpp/{account}/rotate', [SmppController::class, 'rotate'])->name('smpp.rotate');
        });
    });
