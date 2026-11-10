<?php

use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ClientDocumentController;
use App\Http\Controllers\Admin\ClientEntitlementController;
use App\Http\Controllers\Admin\ClientRegistrationController;
use App\Http\Controllers\Admin\IntegrationController;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\LegalAcceptanceController;
use App\Http\Controllers\Admin\LegalComplianceController;
use App\Http\Controllers\Admin\LegalDocumentController;
use App\Http\Controllers\Admin\LoginAuditController;
use App\Http\Controllers\Admin\OrganisationDpaController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\PricingController as AdminPricingController;
use App\Http\Controllers\Admin\QueueController;
use App\Http\Controllers\Admin\RateCardController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SmppController;
use App\Http\Controllers\Admin\SupportArticleController;
use App\Http\Controllers\Admin\SupportCategoryController;
use App\Http\Controllers\Admin\SupportController;
use App\Http\Controllers\Admin\SupportTeamController;
use App\Http\Controllers\Admin\SupportTicketController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\TemplateApprovalController;
use App\Http\Controllers\Admin\TemplateController as AdminTemplateController;
use App\Http\Controllers\Admin\TemplateUsageController;
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
            Route::get('settings/modules', [SettingsController::class, 'modules'])->name('settings.modules');
            Route::put('settings/modules', [SettingsController::class, 'updateModules'])->name('settings.modules.update');
            Route::get('settings/plans', [SettingsController::class, 'plans'])->name('settings.plans');
            Route::put('settings/plans/{client}', [SettingsController::class, 'assignPlan'])->name('settings.plans.assign');

            Route::get('integrations', [IntegrationController::class, 'index'])->name('integrations.index');
            Route::put('integrations', [IntegrationController::class, 'update'])->name('integrations.update');

            Route::get('resource-requests', [SettingsController::class, 'requests'])->name('resource-requests.index');
            Route::put('resource-requests/{resourceRequest}', [SettingsController::class, 'reviewRequest'])->name('resource-requests.review');
        });

        Route::middleware('can:queues.view')->group(function (): void {
            Route::get('system', [SystemController::class, 'index'])->name('system.index');
            Route::get('system/runbook', [SystemController::class, 'runbook'])->name('system.runbook');
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

            Route::get('packages', [PackageController::class, 'index'])->name('packages.index');
            Route::put('packages/{package}', [PackageController::class, 'update'])->name('packages.update');

            Route::get('pricing', [AdminPricingController::class, 'index'])->name('pricing.index');
            Route::put('pricing', [AdminPricingController::class, 'update'])->name('pricing.update');
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

        Route::middleware('can:templates.manage')->group(function (): void {
            Route::get('templates', [AdminTemplateController::class, 'index'])->name('templates.index');
            Route::get('templates/create', [AdminTemplateController::class, 'create'])->name('templates.create');
            Route::post('templates', [AdminTemplateController::class, 'store'])->name('templates.store');
            Route::get('templates/{template}/edit', [AdminTemplateController::class, 'edit'])->name('templates.edit');
            Route::put('templates/{template}', [AdminTemplateController::class, 'update'])->name('templates.update');
            Route::post('templates/{template}/archive', [AdminTemplateController::class, 'archive'])->name('templates.archive');
            Route::delete('templates/{template}', [AdminTemplateController::class, 'destroy'])->name('templates.destroy');
        });

        Route::middleware('can:templates.approve')->group(function (): void {
            Route::get('template-approvals', [TemplateApprovalController::class, 'index'])->name('template-approvals.index');
            Route::post('template-approvals/{template}/approve', [TemplateApprovalController::class, 'approve'])->name('template-approvals.approve');
            Route::post('template-approvals/{template}/reject', [TemplateApprovalController::class, 'reject'])->name('template-approvals.reject');
        });

        Route::middleware('can:templates.view')->group(function (): void {
            Route::get('template-usage', [TemplateUsageController::class, 'index'])->name('template-usage.index');
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

        Route::middleware('can:announcements.view')->group(function (): void {
            Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
            Route::get('announcements/{announcement}/recipients', [AnnouncementController::class, 'recipients'])
                ->name('announcements.recipients');
        });

        Route::middleware('can:announcements.manage')->group(function (): void {
            Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
            Route::post('announcements/{announcement}/send', [AnnouncementController::class, 'send'])->name('announcements.send');
            Route::post('announcements/{announcement}/cancel-schedule', [AnnouncementController::class, 'cancelSchedule'])->name('announcements.cancel-schedule');
            Route::delete('announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
        });

        Route::middleware('can:legal.view')->group(function (): void {
            Route::get('legal-documents', [LegalDocumentController::class, 'index'])->name('legal-documents.index');
            Route::get('legal-document-versions', [LegalComplianceController::class, 'versions'])->name('legal-document-versions.index');
            Route::get('legal-compliance-settings', [LegalComplianceController::class, 'settings'])->name('legal-compliance-settings.index');
            Route::get('legal-notifications', [LegalComplianceController::class, 'notifications'])->name('legal-notifications.index');
            Route::get('legal-compliance-reports', [LegalComplianceController::class, 'reports'])->name('legal-compliance-reports.index');

            Route::get('legal-acceptances', [LegalAcceptanceController::class, 'index'])->name('legal-acceptances.index');
            Route::get('legal-acceptances/export', [LegalAcceptanceController::class, 'export'])->name('legal-acceptances.export');

            Route::get('legal-dpas', [OrganisationDpaController::class, 'index'])->name('legal-dpas.index');
        });

        Route::middleware('can:legal.manage')->group(function (): void {
            Route::post('legal-documents', [LegalDocumentController::class, 'store'])->name('legal-documents.store');
            Route::put('legal-documents/{document}', [LegalDocumentController::class, 'update'])->name('legal-documents.update');
            Route::delete('legal-documents/{document}', [LegalDocumentController::class, 'destroy'])->name('legal-documents.destroy');
            Route::patch('legal-documents/{document}/toggle', [LegalDocumentController::class, 'toggle'])->name('legal-documents.toggle');
            Route::patch('legal-documents/{document}/requirements', [LegalDocumentController::class, 'updateRequirements'])
                ->name('legal-documents.requirements');
            Route::post('legal-documents/{document}/versions', [LegalDocumentController::class, 'storeVersion'])
                ->name('legal-documents.versions.store');

            Route::put('legal-document-versions/{version}', [LegalDocumentController::class, 'updateVersion'])
                ->name('legal-document-versions.update');
            Route::delete('legal-document-versions/{version}', [LegalDocumentController::class, 'destroyVersion'])
                ->name('legal-document-versions.destroy');
            Route::post('legal-document-versions/{version}/publish', [LegalDocumentController::class, 'publish'])
                ->name('legal-document-versions.publish');
            Route::post('legal-document-versions/{version}/archive', [LegalDocumentController::class, 'archive'])
                ->name('legal-document-versions.archive');
            Route::post('legal-document-versions/{version}/notify', [LegalDocumentController::class, 'notify'])
                ->name('legal-document-versions.notify');

            Route::put('legal-dpas/{client}', [OrganisationDpaController::class, 'update'])->name('legal-dpas.update');
        });

        /*
        |----------------------------------------------------------------------
        | Support desk
        |----------------------------------------------------------------------
        |
        | Enquiries and tickets share one register but are read on the screen
        | the team expects: a public enquiry is answered once, a client ticket is
        | worked until it is resolved. Teams, categories and the knowledge base
        | are configuration, so they sit behind the manage permission.
        |
        */

        Route::middleware('can:support.view')->group(function (): void {
            Route::get('support/enquiries', [SupportController::class, 'enquiries'])->name('support.enquiries.index');
            Route::get('support/enquiries/{ticket:reference}', [SupportTicketController::class, 'show'])
                ->name('support.enquiries.show');

            Route::get('support/tickets', [SupportController::class, 'tickets'])->name('support.tickets.index');
            Route::get('support/tickets/{ticket:reference}', [SupportTicketController::class, 'show'])
                ->name('support.tickets.show');
            Route::get('support/tickets/{ticket:reference}/attachments/{attachment}', [SupportTicketController::class, 'downloadAttachment'])
                ->name('support.tickets.attachments.download');

            Route::get('support/categories', [SupportCategoryController::class, 'index'])->name('support.categories.index');
            Route::get('support/teams', [SupportTeamController::class, 'index'])->name('support.teams.index');
            Route::get('support/notifications', [SupportController::class, 'notifications'])->name('support.notifications.index');
            Route::get('support/knowledge-base', [SupportArticleController::class, 'index'])->name('support.knowledge-base.index');
            Route::get('support/reports', [SupportController::class, 'reports'])->name('support.reports.index');
        });

        Route::middleware('can:support.manage')->group(function (): void {
            Route::post('support/tickets/{ticket:reference}/reply', [SupportTicketController::class, 'reply'])
                ->name('support.tickets.reply');
            Route::put('support/tickets/{ticket:reference}', [SupportTicketController::class, 'update'])
                ->name('support.tickets.update');

            Route::post('support/enquiries/{ticket:reference}/reply', [SupportTicketController::class, 'reply'])
                ->name('support.enquiries.reply');
            Route::put('support/enquiries/{ticket:reference}', [SupportTicketController::class, 'update'])
                ->name('support.enquiries.update');

            Route::put('support/notifications', [SupportController::class, 'updateNotifications'])
                ->name('support.notifications.update');

            Route::post('support/categories', [SupportCategoryController::class, 'store'])->name('support.categories.store');
            Route::put('support/categories/{category}', [SupportCategoryController::class, 'update'])->name('support.categories.update');
            Route::delete('support/categories/{category}', [SupportCategoryController::class, 'destroy'])->name('support.categories.destroy');

            Route::post('support/teams', [SupportTeamController::class, 'store'])->name('support.teams.store');
            Route::put('support/teams/{team}', [SupportTeamController::class, 'update'])->name('support.teams.update');
            Route::delete('support/teams/{team}', [SupportTeamController::class, 'destroy'])->name('support.teams.destroy');

            Route::post('support/knowledge-base', [SupportArticleController::class, 'store'])->name('support.knowledge-base.store');
            Route::put('support/knowledge-base/{article}', [SupportArticleController::class, 'update'])->name('support.knowledge-base.update');
            Route::delete('support/knowledge-base/{article}', [SupportArticleController::class, 'destroy'])->name('support.knowledge-base.destroy');
        });
    });
