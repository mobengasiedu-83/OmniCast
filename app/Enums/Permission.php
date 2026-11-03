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
    case ViewWallet = 'wallet.view';
    case ManageWallet = 'wallet.manage';
    case TopUpWallet = 'wallet.top-up';
    case ViewRates = 'rates.view';
    case ManageRates = 'rates.manage';
    case ViewReports = 'reports.view';
    case ExportReports = 'reports.export';

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
            self::ViewWallet => 'View Wallet',
            self::ManageWallet => 'Manage Wallet',
            self::TopUpWallet => 'Top Up Wallet',
            self::ViewRates => 'View Rates',
            self::ManageRates => 'Manage Rates',
            self::ViewReports => 'View Reports',
            self::ExportReports => 'Export Reports',
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
