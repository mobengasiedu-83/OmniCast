<?php

namespace App\Enums;

/**
 * The granular abilities granted to a role.
 */
enum Permission: string
{
    case ManageClients = 'clients.manage';
    case ApproveClients = 'clients.approve';
    case ManageUsers = 'users.manage';
    case ViewContacts = 'contacts.view';
    case ManageContacts = 'contacts.manage';
    case ImportContacts = 'contacts.import';
    case ViewCampaigns = 'campaigns.view';
    case ManageCampaigns = 'campaigns.manage';
    case ApproveCampaigns = 'campaigns.approve';
    case LaunchCampaigns = 'campaigns.launch';
    case ViewSenderIds = 'sender-ids.view';
    case ManageSenderIds = 'sender-ids.manage';
    case ApproveSenderIds = 'sender-ids.approve';
    case ViewTemplates = 'templates.view';
    case ManageTemplates = 'templates.manage';
    case ApproveTemplates = 'templates.approve';
    case ManageTemplateCategories = 'template-categories.manage';
    case ViewWallet = 'wallet.view';
    case ManageWallet = 'wallet.manage';
    case TopUpWallet = 'wallet.top-up';
    case ViewRates = 'rates.view';
    case ManageRates = 'rates.manage';
    case ViewReports = 'reports.view';
    case ExportReports = 'reports.export';
    case ViewQueues = 'queues.view';
    case ViewWebhooks = 'webhooks.view';
    case ManageWebhooks = 'webhooks.manage';
    case ViewSmpp = 'smpp.view';
    case ManageSmpp = 'smpp.manage';
    case ViewWhatsAppProviders = 'whatsapp.providers.view';
    case ManageWhatsAppProviders = 'whatsapp.providers.manage';
    case TestWhatsAppProviders = 'whatsapp.providers.test';
    case ViewWhatsAppConnectors = 'whatsapp.connectors.view';
    case ManageWhatsAppConnectors = 'whatsapp.connectors.manage';
    case ApproveWhatsAppConnectors = 'whatsapp.connectors.approve';
    case ApproveWhatsAppTemplates = 'whatsapp.templates.approve';
    case ViewAutomations = 'automations.view';
    case ManageAutomations = 'automations.manage';
    case ViewAnnouncements = 'announcements.view';
    case ManageAnnouncements = 'announcements.manage';
    case ViewLegal = 'legal.view';
    case ManageLegal = 'legal.manage';
    case ViewSupport = 'support.view';
    case ManageSupport = 'support.manage';

    public function label(): string
    {
        return match ($this) {
            self::ManageClients => 'Manage Clients',
            self::ApproveClients => 'Approve Clients',
            self::ManageUsers => 'Manage Users',
            self::ViewContacts => 'View Contacts',
            self::ManageContacts => 'Manage Contacts',
            self::ImportContacts => 'Import Contacts',
            self::ViewCampaigns => 'View Campaigns',
            self::ManageCampaigns => 'Manage Campaigns',
            self::ApproveCampaigns => 'Approve Campaigns',
            self::LaunchCampaigns => 'Launch Campaigns',
            self::ViewSenderIds => 'View Sender IDs',
            self::ManageSenderIds => 'Manage Sender IDs',
            self::ApproveSenderIds => 'Approve Sender IDs',
            self::ViewTemplates => 'View Templates',
            self::ManageTemplates => 'Manage Templates',
            self::ApproveTemplates => 'Approve Templates',
            self::ManageTemplateCategories => 'Manage Template Categories',
            self::ViewWallet => 'View Wallet',
            self::ManageWallet => 'Manage Wallet',
            self::TopUpWallet => 'Top Up Wallet',
            self::ViewRates => 'View Rates',
            self::ManageRates => 'Manage Rates',
            self::ViewReports => 'View Reports',
            self::ExportReports => 'Export Reports',
            self::ViewQueues => 'View Delivery Queues',
            self::ViewWebhooks => 'View Webhooks',
            self::ManageWebhooks => 'Manage Webhooks',
            self::ViewSmpp => 'View SMPP Sessions',
            self::ManageSmpp => 'Manage SMPP Accounts',
            self::ViewWhatsAppProviders => 'View WhatsApp Providers',
            self::ManageWhatsAppProviders => 'Manage WhatsApp Providers',
            self::TestWhatsAppProviders => 'Test WhatsApp Providers',
            self::ViewWhatsAppConnectors => 'View WhatsApp Connectors',
            self::ManageWhatsAppConnectors => 'Manage WhatsApp Connectors',
            self::ApproveWhatsAppConnectors => 'Approve WhatsApp Connectors',
            self::ApproveWhatsAppTemplates => 'Approve WhatsApp Templates',
            self::ViewAutomations => 'View Automations',
            self::ManageAutomations => 'Manage Automations',
            self::ViewAnnouncements => 'View Announcements',
            self::ManageAnnouncements => 'Send Announcements',
            self::ViewLegal => 'View Legal & Compliance',
            self::ManageLegal => 'Manage Legal & Compliance',
            self::ViewSupport => 'View Support Desk',
            self::ManageSupport => 'Manage Support Desk',
        };
    }

    /**
     * The permission values, as stored by the permission package.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $permission): string => $permission->value, self::cases());
    }
}
