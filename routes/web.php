<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\Auth\InvitationAcceptanceController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ClientRegistrationController;
use App\Http\Controllers\ContactComplianceController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactDndController;
use App\Http\Controllers\ContactListController;
use App\Http\Controllers\ContactUsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailAttachmentController;
use App\Http\Controllers\EmailImageController;
use App\Http\Controllers\EmailWebhookController;
use App\Http\Controllers\HelpCentreController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\Legal\LegalPageController;
use App\Http\Controllers\MetaWhatsAppConnectionController;
use App\Http\Controllers\MobileMoneyCheckoutController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaystackWebhookController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SenderIdController;
use App\Http\Controllers\Settings\AutomationRunController;
use App\Http\Controllers\Settings\AutomationStepController;
use App\Http\Controllers\Settings\AutomationWorkflowController;
use App\Http\Controllers\Settings\ClientSettingsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SmsWebhookController;
use App\Http\Controllers\Support\ContactSupportController;
use App\Http\Controllers\Support\KnowledgeBaseController;
use App\Http\Controllers\Support\SupportCentreController;
use App\Http\Controllers\Support\SupportTicketController;
use App\Http\Controllers\TemplateCategoryController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\VoiceWebhookController;
use App\Http\Controllers\WalletCheckoutController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\Webhooks\MetaWhatsAppWebhookController;
use App\Http\Controllers\Webhooks\WhatsAppProviderEventController;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\WhatsAppMediaController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('pricing', PricingController::class)->name('pricing');

Route::inertia('user-manual', 'user-manual')->name('user-manual');

Route::inertia('api-docs', 'api-docs')->name('api-docs');

/*
|--------------------------------------------------------------------------
| Contact and help
|--------------------------------------------------------------------------
|
| A visitor can reach a person and read the answers without an account: the
| contact form writes straight into the support register, and the help centre
| serves the published articles. Both are challenged with Turnstile and rate
| limited per address.
|
*/

Route::get('contact', [ContactUsController::class, 'show'])->name('contact.show');

Route::post('contact', [ContactUsController::class, 'store'])
    ->middleware(['turnstile', 'throttle:support-enquiries'])
    ->name('contact.store');

Route::get('help', [HelpCentreController::class, 'index'])->name('help.index');

Route::get('help/{article:slug}', [HelpCentreController::class, 'show'])->name('help.show');

/*
|--------------------------------------------------------------------------
| Policies
|--------------------------------------------------------------------------
|
| The five legal documents are readable without an account: a prospective
| customer reviews them before applying, and a regulator never has to be given
| a login. Only documents with a published version render; anything else 404s.
|
*/

Route::get('legal', [LegalPageController::class, 'index'])->name('legal.index');

Route::get('legal/{slug}', [LegalPageController::class, 'show'])->name('legal.show');

Route::get('legal/{slug}/print', [LegalPageController::class, 'print'])->name('legal.print');

Route::get('stop-messages/{contact}', [ContactDndController::class, 'show'])
    ->middleware('signed')
    ->name('contacts.dnd.request');

Route::post('stop-messages/{contact}', [ContactDndController::class, 'store'])
    ->middleware(['signed', 'throttle:30,1'])
    ->name('contacts.dnd.request.store');

Route::post('webhooks/voice', VoiceWebhookController::class)->name('webhooks.voice');

Route::match(['get', 'post'], 'webhooks/sms', SmsWebhookController::class)->name('webhooks.sms');

Route::match(['get', 'post'], 'webhooks/email', EmailWebhookController::class)->name('webhooks.email');

Route::post('webhooks/paystack', PaystackWebhookController::class)->name('webhooks.paystack');
Route::post('webhooks/whatsapp/pro-plus', WhatsAppProviderEventController::class)
    ->middleware('throttle:60,1')
    ->name('webhooks.whatsapp.pro-plus');
Route::get('webhooks/whatsapp/meta', [MetaWhatsAppWebhookController::class, 'verify'])
    ->name('webhooks.whatsapp.meta.verify');
Route::post('webhooks/whatsapp/meta', [MetaWhatsAppWebhookController::class, 'receive'])
    ->middleware('throttle:120,1')->name('webhooks.whatsapp.meta.receive');

Route::get('whatsapp/media/{asset}', [WhatsAppMediaController::class, 'show'])
    ->middleware(['signed', 'throttle:60,1'])
    ->name('whatsapp.media.show');

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
    Route::middleware('module:whatsapp')->group(function (): void {
        Route::get('whatsapp', [WhatsAppController::class, 'index'])->name('whatsapp.index');
        Route::post('whatsapp/meta/complete', [MetaWhatsAppConnectionController::class, 'complete'])
            ->middleware('throttle:6,1')->name('whatsapp.meta.complete');
        Route::get('whatsapp/messages/export', [WhatsAppController::class, 'exportMessages'])->name('whatsapp.messages.export');
        Route::post('whatsapp/consents', [WhatsAppController::class, 'storeConsent'])->name('whatsapp.consents.store');
        Route::delete('whatsapp/consents/{consent}', [WhatsAppController::class, 'revokeConsent'])->name('whatsapp.consents.revoke');
        Route::post('whatsapp/campaigns', [WhatsAppController::class, 'storeCampaign'])->name('whatsapp.campaigns.store');
        Route::post('whatsapp/campaigns/{campaign}/dispatch', [WhatsAppController::class, 'dispatchCampaign'])->name('whatsapp.campaigns.dispatch');
        Route::post('whatsapp/campaigns/{campaign}/test', [WhatsAppController::class, 'testCampaign'])->name('whatsapp.campaigns.test');
        Route::post('whatsapp/campaigns/{campaign}/pause', [WhatsAppController::class, 'pauseCampaign'])->name('whatsapp.campaigns.pause');
        Route::post('whatsapp/campaigns/{campaign}/resume', [WhatsAppController::class, 'resumeCampaign'])->name('whatsapp.campaigns.resume');
        Route::post('whatsapp/campaigns/{campaign}/cancel', [WhatsAppController::class, 'cancelCampaign'])->name('whatsapp.campaigns.cancel');
        Route::post('whatsapp/connectors', [WhatsAppController::class, 'requestConnector'])->name('whatsapp.connectors.request');
        Route::post('whatsapp/media', [WhatsAppController::class, 'upload'])->middleware('throttle:30,1')->name('whatsapp.media.store');
        Route::get('whatsapp/media/{asset}/url', [WhatsAppController::class, 'mediaUrl'])->name('whatsapp.media.url');
    });
    Route::get('settings/organisation', [ClientSettingsController::class, 'edit'])->name('organisation-settings.edit');
    Route::put('settings/organisation', [ClientSettingsController::class, 'update'])->name('organisation-settings.update');
    Route::post('settings/organisation/documents', [ClientSettingsController::class, 'storeDocument'])->name('organisation-settings.documents.store');
    Route::get('settings/resource-requests', [ClientSettingsController::class, 'requests'])->name('organisation-settings.requests');
    Route::post('settings/resource-requests', [ClientSettingsController::class, 'storeRequest'])->name('organisation-settings.requests.store');
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::get('campaigns/create', [CampaignController::class, 'create'])->name('campaigns.create');
    Route::post('campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
    Route::get('campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
    Route::get('campaigns/{campaign}/edit', [CampaignController::class, 'edit'])->name('campaigns.edit');
    Route::put('campaigns/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
    Route::delete('campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');
    Route::post('campaigns/{campaign}/submit', [CampaignController::class, 'submit'])->name('campaigns.submit');
    Route::post('campaigns/{campaign}/approve', [CampaignController::class, 'approve'])->name('campaigns.approve');
    Route::post('campaigns/{campaign}/reject', [CampaignController::class, 'reject'])->name('campaigns.reject');
    Route::post('campaigns/{campaign}/audio/approve', [CampaignController::class, 'approveAudio'])->name('campaigns.audio.approve');
    Route::post('campaigns/{campaign}/audio/reject', [CampaignController::class, 'rejectAudio'])->name('campaigns.audio.reject');
    Route::post('campaigns/{campaign}/launch', [CampaignController::class, 'launch'])->name('campaigns.launch');
    Route::post('campaigns/{campaign}/cancel', [CampaignController::class, 'cancel'])->name('campaigns.cancel');
    Route::post('campaigns/{campaign}/duplicate', [CampaignController::class, 'duplicate'])->name('campaigns.duplicate');

    Route::get('campaigns/{campaign}/attachments/{attachment}/download', [EmailAttachmentController::class, 'download'])
        ->name('campaigns.attachments.download');

    Route::post('email/images', [EmailImageController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('email.images.store');

    Route::get('contacts/lists', [ContactListController::class, 'index'])->name('contacts.lists.index');
    Route::post('contacts/lists', [ContactListController::class, 'store'])->name('contacts.lists.store');
    Route::get('contacts/lists/{list}', [ContactListController::class, 'show'])->name('contacts.lists.show');
    Route::put('contacts/lists/{list}', [ContactListController::class, 'update'])->name('contacts.lists.update');
    Route::delete('contacts/lists/{list}', [ContactListController::class, 'destroy'])->name('contacts.lists.destroy');
    Route::post('contacts/lists/{list}/import', [ContactListController::class, 'import'])->name('contacts.lists.import');
    Route::post('contacts/lists/{list}/contacts', [ContactListController::class, 'attach'])->name('contacts.lists.contacts.store');
    Route::delete('contacts/lists/{list}/contacts', [ContactListController::class, 'detach'])->name('contacts.lists.contacts.destroy');

    Route::get('contacts/audiences', [ContactComplianceController::class, 'audiences'])->name('contacts.audiences.index');
    Route::post('contacts/audiences/tags', [ContactComplianceController::class, 'storeTag'])->name('contacts.audiences.tags.store');
    Route::delete('contacts/audiences/tags/{tag}', [ContactComplianceController::class, 'destroyTag'])->name('contacts.audiences.tags.destroy');
    Route::post('contacts/audiences/lists', [ContactListController::class, 'store'])->name('contacts.audiences.lists.store');
    Route::delete('contacts/audiences/lists/{list}', [ContactListController::class, 'destroy'])->name('contacts.audiences.lists.destroy');
    Route::post('contacts/audiences/segments', [ContactComplianceController::class, 'storeSegment'])->name('contacts.audiences.segments.store');
    Route::put('contacts/audiences/segments/{segment}', [ContactComplianceController::class, 'updateSegment'])->name('contacts.audiences.segments.update');
    Route::delete('contacts/audiences/segments/{segment}', [ContactComplianceController::class, 'destroySegment'])->name('contacts.audiences.segments.destroy');
    Route::post('contacts/audiences/duplicates/merge', [ContactComplianceController::class, 'mergeDuplicates'])->name('contacts.audiences.duplicates.merge');
    Route::post('contacts/audiences/suppressions', [ContactComplianceController::class, 'storeSuppression'])->name('contacts.audiences.suppressions.store');
    Route::delete('contacts/audiences/suppressions/{suppression}', [ContactComplianceController::class, 'liftSuppression'])->name('contacts.audiences.suppressions.lift');
    Route::get('contacts/dnd', [ContactComplianceController::class, 'dnd'])->name('contacts.dnd.registry');
    Route::get('contacts/import', [ContactController::class, 'showImport'])->name('contacts.import.create');
    Route::get('contacts/import/template', [ContactController::class, 'template'])->name('contacts.import.template');
    Route::post('contacts/import', [ContactController::class, 'import'])->name('contacts.import');
    Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::get('contacts/create', [ContactController::class, 'create'])->name('contacts.create');
    Route::post('contacts', [ContactController::class, 'store'])->name('contacts.store');
    Route::post('contacts/bulk', [ContactController::class, 'bulk'])->name('contacts.bulk');
    Route::post('contacts/{contact}/dnd', [ContactController::class, 'dnd'])->name('contacts.dnd');
    Route::get('contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');
    Route::get('contacts/{contact}/edit', [ContactController::class, 'edit'])->name('contacts.edit');
    Route::put('contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
    Route::delete('contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');

    Route::get('sender-ids', [SenderIdController::class, 'index'])->name('sender-ids.index');
    Route::get('sender-ids/create', [SenderIdController::class, 'create'])->name('sender-ids.create');
    Route::post('sender-ids', [SenderIdController::class, 'store'])->name('sender-ids.store');
    Route::get('sender-ids/{senderId}/edit', [SenderIdController::class, 'edit'])->name('sender-ids.edit');
    Route::put('sender-ids/{senderId}', [SenderIdController::class, 'update'])->name('sender-ids.update');
    Route::post('sender-ids/{senderId}/approve', [SenderIdController::class, 'approve'])->name('sender-ids.approve');
    Route::post('sender-ids/{senderId}/reject', [SenderIdController::class, 'reject'])->name('sender-ids.reject');
    Route::delete('sender-ids/{senderId}', [SenderIdController::class, 'destroy'])->name('sender-ids.destroy');

    Route::get('templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::get('templates/create', [TemplateController::class, 'create'])->name('templates.create');
    Route::post('templates', [TemplateController::class, 'store'])->name('templates.store');
    Route::get('templates/{template}/edit', [TemplateController::class, 'edit'])->name('templates.edit');
    Route::put('templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
    Route::delete('templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');
    Route::post('templates/{template}/customise', [TemplateController::class, 'customise'])->name('templates.customise');
    Route::post('templates/{template}/favourite', [TemplateController::class, 'favourite'])->name('templates.favourite');

    Route::get('template-categories', [TemplateCategoryController::class, 'index'])->name('template-categories.index');
    Route::post('template-categories', [TemplateCategoryController::class, 'store'])->name('template-categories.store');
    Route::put('template-categories/{category}', [TemplateCategoryController::class, 'update'])->name('template-categories.update');
    Route::delete('template-categories/{category}', [TemplateCategoryController::class, 'destroy'])->name('template-categories.destroy');

    Route::get('wallet', [WalletController::class, 'index'])->name('wallet.index');
    Route::post('wallet/top-up', [WalletController::class, 'topUp'])->name('wallet.top-up');

    Route::post('wallet/checkout', [WalletCheckoutController::class, 'store'])->name('wallet.checkout.store');
    Route::get('wallet/checkout/callback', [WalletCheckoutController::class, 'callback'])
        ->name('wallet.checkout.callback');
    Route::get('wallet/checkout/{payment}/cancel', [WalletCheckoutController::class, 'cancel'])
        ->name('wallet.checkout.cancel');

    Route::get('wallet/mobile-money', [MobileMoneyCheckoutController::class, 'create'])
        ->name('wallet.mobile-money.create');
    Route::post('wallet/mobile-money', [MobileMoneyCheckoutController::class, 'store'])
        ->name('wallet.mobile-money.store');
    Route::get('wallet/mobile-money/{payment}', [MobileMoneyCheckoutController::class, 'show'])
        ->name('wallet.mobile-money.show');
    Route::post('wallet/mobile-money/{payment}/otp', [MobileMoneyCheckoutController::class, 'submitOtp'])
        ->name('wallet.mobile-money.otp');
    Route::post('wallet/mobile-money/{payment}/refresh', [MobileMoneyCheckoutController::class, 'refresh'])
        ->name('wallet.mobile-money.refresh');

    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/{invoice}/download', [InvoiceController::class, 'download'])
        ->name('invoices.download');

    Route::get('automations', [AutomationWorkflowController::class, 'index'])->name('automations.index');
    Route::post('automations', [AutomationWorkflowController::class, 'store'])->name('automations.store');

    Route::get('automations/runs', [AutomationRunController::class, 'index'])->name('automations.runs.index');
    Route::get('automations/runs/{run}', [AutomationRunController::class, 'show'])->name('automations.runs.show');

    Route::get('automations/{workflow}', [AutomationWorkflowController::class, 'show'])->name('automations.show');
    Route::put('automations/{workflow}', [AutomationWorkflowController::class, 'update'])->name('automations.update');
    Route::patch('automations/{workflow}/toggle', [AutomationWorkflowController::class, 'toggle'])->name('automations.toggle');
    Route::delete('automations/{workflow}', [AutomationWorkflowController::class, 'destroy'])->name('automations.destroy');

    Route::post('automations/{workflow}/steps', [AutomationStepController::class, 'store'])->name('automations.steps.store');
    Route::put('automations/{workflow}/steps/{step}', [AutomationStepController::class, 'update'])->name('automations.steps.update');
    Route::patch('automations/{workflow}/steps/{step}/move', [AutomationStepController::class, 'move'])->name('automations.steps.move');
    Route::delete('automations/{workflow}/steps/{step}', [AutomationStepController::class, 'destroy'])->name('automations.steps.destroy');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/campaigns', [ReportController::class, 'campaigns'])->name('reports.campaigns');
    Route::get('reports/contacts', [ReportController::class, 'contacts'])->name('reports.contacts');
    Route::get('reports/voice', [ReportController::class, 'voice'])->name('reports.voice')->middleware('module:voice');
    Route::get('reports/sms', [ReportController::class, 'sms'])->name('reports.sms')->middleware('module:sms');
    Route::get('reports/email', [ReportController::class, 'email'])->name('reports.email')->middleware('module:email');

    Route::get('reports/exports', [ReportController::class, 'exportHistory'])->name('reports.exports.index');
    Route::post('reports/exports', [ReportController::class, 'storeExport'])->name('reports.exports.store');
    Route::get('reports/exports/{export}/download', [ReportController::class, 'downloadExport'])
        ->name('reports.exports.download');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::delete('notifications/read', [NotificationController::class, 'clearRead'])->name('notifications.clear-read');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('notifications/{notification}/unread', [NotificationController::class, 'markUnread'])->name('notifications.unread');
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    /*
    |--------------------------------------------------------------------------
    | Support
    |--------------------------------------------------------------------------
    |
    | A client's own conversations. Every route is scoped to the requester's
    | organisation by the ticket policy. Opening a ticket is challenged with
    | Turnstile because it writes a conversation the platform team has to answer.
    |
    */

    Route::prefix('support')->name('support.')->group(function (): void {
        Route::get('/', [SupportCentreController::class, 'index'])->name('index');

        Route::get('tickets', [SupportTicketController::class, 'index'])->name('tickets.index');
        Route::get('tickets/create', [SupportTicketController::class, 'create'])->name('tickets.create');
        Route::post('tickets', [SupportTicketController::class, 'store'])
            ->middleware(['turnstile', 'throttle:support-replies'])
            ->name('tickets.store');

        Route::get('tickets/{ticket:reference}', [SupportTicketController::class, 'show'])->name('tickets.show');
        Route::post('tickets/{ticket:reference}/reply', [SupportTicketController::class, 'reply'])
            ->middleware('throttle:support-replies')
            ->name('tickets.reply');
        Route::post('tickets/{ticket:reference}/close', [SupportTicketController::class, 'close'])->name('tickets.close');
        Route::get('tickets/{ticket:reference}/attachments/{attachment}', [SupportTicketController::class, 'downloadAttachment'])
            ->name('tickets.attachments.download');

        Route::get('knowledge-base', [KnowledgeBaseController::class, 'index'])->name('knowledge-base.index');
        Route::get('knowledge-base/{article:slug}', [KnowledgeBaseController::class, 'show'])->name('knowledge-base.show');

        Route::get('contact', ContactSupportController::class)->name('contact');
    });
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
