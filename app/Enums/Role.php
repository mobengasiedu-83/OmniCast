<?php

namespace App\Enums;

/**
 * The platform roles. Platform roles are not tied to a single client.
 */
enum Role: string
{
    case SuperAdmin = 'super-admin';
    case ClientAdmin = 'client-admin';
    case ClientOperator = 'client-operator';
    case ComplianceOfficer = 'compliance-officer';
    case FinanceOfficer = 'finance-officer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::ClientAdmin => 'Client Admin',
            self::ClientOperator => 'Client Operator',
            self::ComplianceOfficer => 'Compliance Officer',
            self::FinanceOfficer => 'Finance Officer',
        };
    }

    /**
     * Whether the role belongs to the platform team rather than a client tenant.
     */
    public function isPlatformRole(): bool
    {
        return in_array($this, [self::SuperAdmin, self::ComplianceOfficer, self::FinanceOfficer], true);
    }

    /**
     * Whether the role may act across every client.
     */
    public function bypassesClientScope(): bool
    {
        return $this === self::SuperAdmin;
    }

    /**
     * The permissions granted to the role.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => Permission::cases(),
            self::ClientAdmin => [
                Permission::ManageUsers,
                Permission::ViewContacts,
                Permission::ManageContacts,
                Permission::ImportContacts,
                Permission::ViewCampaigns,
                Permission::ManageCampaigns,
                Permission::LaunchCampaigns,
                Permission::ViewSenderIds,
                Permission::ManageSenderIds,
                Permission::ViewWallet,
                Permission::TopUpWallet,
                Permission::ViewRates,
                Permission::ViewReports,
                Permission::ExportReports,
                Permission::ViewWebhooks,
                Permission::ManageWebhooks,
                Permission::ViewSmpp,
            ],
            self::ClientOperator => [
                Permission::ViewContacts,
                Permission::ManageContacts,
                Permission::ImportContacts,
                Permission::ViewCampaigns,
                Permission::ManageCampaigns,
                Permission::ViewSenderIds,
                Permission::ViewWallet,
                Permission::ViewReports,
                Permission::ViewWebhooks,
            ],
            self::ComplianceOfficer => [
                Permission::ManageClients,
                Permission::ApproveClients,
                Permission::ViewContacts,
                Permission::ViewCampaigns,
                Permission::ApproveCampaigns,
                Permission::ViewSenderIds,
                Permission::ApproveSenderIds,
                Permission::ViewReports,
                Permission::ViewWebhooks,
                Permission::ViewSmpp,
            ],
            self::FinanceOfficer => [
                Permission::ManageClients,
                Permission::ApproveClients,
                Permission::ViewCampaigns,
                Permission::ViewWallet,
                Permission::ManageWallet,
                Permission::ViewRates,
                Permission::ManageRates,
                Permission::ViewReports,
                Permission::ExportReports,
            ],
        };
    }

    /**
     * The roles that belong to a client tenant.
     *
     * @return list<self>
     */
    public static function clientRoles(): array
    {
        return [self::ClientAdmin, self::ClientOperator];
    }

    /**
     * The roles that belong to the platform team.
     *
     * @return list<self>
     */
    public static function platformRoles(): array
    {
        return [self::SuperAdmin, self::ComplianceOfficer, self::FinanceOfficer];
    }

    /**
     * The role values, as stored by the permission package.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $role): string => $role->value, self::cases());
    }
}
