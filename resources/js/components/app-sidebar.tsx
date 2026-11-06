import { Link, usePage } from '@inertiajs/react';
import {
    Banknote,
    ChartLine,
    ClipboardCheck,
    CreditCard,
    Download,
    FileText,
    Fingerprint,
    Gauge,
    KeyRound,
    LayoutDashboard,
    Mail,
    MailPlus,
    Megaphone,
    MessageSquareText,
    MonitorSmartphone,
    PackageCheck,
    PackagePlus,
    Layers3,
    PhoneCall,
    ReceiptText,
    ScrollText,
    ServerCog,
    Settings,
    ShieldBan,
    ShieldCheck,
    Sparkles,
    Tags,
    UserRoundCog,
    Users,
    UsersRound,
    Wallet,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import PoweredBy from '@/components/powered-by';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { urls } from '@/lib/urls';
import clients from '@/routes/admin/clients';
import registrations from '@/routes/admin/client-registrations';
import type { Auth, NavItem } from '@/types';

const platformNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: urls.dashboard,
        icon: LayoutDashboard,
    },
];

const messagingNavItems: NavItem[] = [
    {
        title: 'Campaigns',
        href: urls.campaigns.index,
        icon: Megaphone,
    },
    {
        title: 'Contacts',
        href: urls.contacts.index,
        icon: Users,
        children: [
            {
                title: 'All contacts',
                href: urls.contacts.index,
                icon: Users,
            },
            {
                title: 'Audiences',
                href: urls.contacts.audiences,
                icon: Tags,
            },
            {
                title: 'DND',
                href: urls.contacts.dndRegistry,
                icon: ShieldBan,
            },
        ],
    },
    {
        title: 'Sender IDs',
        href: urls.senderIds.index,
        icon: Fingerprint,
    },
    {
        title: 'Voice calls',
        href: urls.reports.voice,
        icon: PhoneCall,
    },
    {
        title: 'SMS messages',
        href: urls.reports.sms,
        icon: MessageSquareText,
    },
    {
        title: 'Email messages',
        href: urls.reports.email,
        icon: Mail,
    },
];

const identityNavItems: NavItem[] = [
    {
        title: 'Global settings',
        href: urls.admin.settings.index,
        icon: Settings,
    },
    {
        title: 'Client plans',
        href: urls.admin.settings.plans,
        icon: Layers3,
    },
    {
        title: 'Resource requests',
        href: urls.admin.resourceRequests.index,
        icon: PackagePlus,
    },
    {
        title: 'All users',
        href: urls.admin.users.index,
        icon: UsersRound,
    },
    {
        title: 'Invitations',
        href: urls.admin.invitations.index,
        icon: MailPlus,
    },
    {
        title: 'Roles',
        href: urls.admin.roles.index,
        icon: UserRoundCog,
    },
    {
        title: 'Permissions',
        href: urls.admin.permissions.index,
        icon: ShieldCheck,
    },
    {
        title: 'Sessions',
        href: urls.admin.sessions.index,
        icon: MonitorSmartphone,
    },
    {
        title: 'Login audit',
        href: urls.admin.loginAudit.index,
        icon: ScrollText,
    },
];

const operationsNavItems: NavItem[] = [
    {
        title: 'Delivery queues',
        href: urls.admin.queues.index,
        icon: Gauge,
    },
];

/** The tenant's own analytics screens. */
const reportsNavItems: NavItem[] = [
    {
        title: 'Reports',
        href: urls.reports.index,
        icon: ChartLine,
    },
    {
        title: 'Report exports',
        href: urls.reports.exports.index,
        icon: Download,
    },
];

/** The platform team's cross-client analytics screens. */
const platformReportsNavItems: NavItem[] = [
    {
        title: 'Executive report',
        href: urls.admin.reports.index,
        icon: ChartLine,
    },
    {
        title: 'Client performance',
        href: urls.admin.reports.clients,
        icon: Users,
    },
    {
        title: 'Revenue',
        href: urls.admin.reports.revenue,
        icon: Banknote,
    },
    {
        title: 'Provider performance',
        href: urls.admin.reports.providers,
        icon: ServerCog,
    },
    {
        title: 'Report exports',
        href: urls.admin.reports.exports.index,
        icon: Download,
    },
];

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;

    const isPlatformUser = auth?.isPlatformUser ?? false;
    const permissions = auth?.permissions ?? [];
    const canManageUsers = permissions.includes('users.manage');
    const canManageClients = permissions.includes('clients.manage');
    const canReviewClients = permissions.includes('clients.approve');
    const canReviewCampaigns = permissions.includes('campaigns.approve');
    const canReviewSenderIds = permissions.includes('sender-ids.approve');
    const canViewWallet = permissions.includes('wallet.view');
    const canManageWallet =
        isPlatformUser && permissions.includes('wallet.manage');
    const canViewRates = isPlatformUser && permissions.includes('rates.view');
    const canViewQueues = isPlatformUser && permissions.includes('queues.view');
    const canViewReports = permissions.includes('reports.view');
    const isClientUser = !isPlatformUser && (auth?.clientId ?? null) !== null;

    const billingNavItems: NavItem[] = [
        ...(canViewWallet
            ? [
                  {
                      title: 'Wallet',
                      href: urls.wallet.index,
                      icon: Wallet,
                  },
              ]
            : []),
        ...(canViewWallet && !isPlatformUser
            ? [
                  {
                      title: 'Purchases',
                      href: urls.invoices.index,
                      icon: ReceiptText,
                  },
              ]
            : []),
        ...(canManageWallet
            ? [
                  {
                      title: 'Payments',
                      href: urls.admin.payments.index,
                      icon: CreditCard,
                  },
                  {
                      title: 'Invoices',
                      href: urls.admin.invoices.index,
                      icon: ReceiptText,
                  },
                  {
                      title: 'Wallet packages',
                      href: urls.admin.packages.index,
                      icon: PackageCheck,
                  },
                  {
                      title: 'Dynamic pricing',
                      href: urls.admin.pricing.index,
                      icon: Tags,
                  },
              ]
            : []),
        ...(canViewRates
            ? [
                  {
                      title: 'Rate cards',
                      href: urls.admin.rateCards.index,
                      icon: Tags,
                  },
              ]
            : []),
    ];

    const accountNavItems: NavItem[] = [
        ...(isClientUser
            ? [
                  {
                      title: 'Organisation settings',
                      href: urls.account.organisation.index,
                      icon: Settings,
                  },
                  {
                      title: 'Resource requests',
                      href: urls.account.resourceRequests.index,
                      icon: PackagePlus,
                  },
              ]
            : []),
        ...(isClientUser && canManageUsers
            ? [
                  {
                      title: 'Team',
                      href: urls.account.team.index,
                      icon: UsersRound,
                  },
              ]
            : []),
        {
            title: 'Sessions',
            href: urls.account.sessions.index,
            icon: MonitorSmartphone,
        },
        {
            title: 'API tokens',
            href: urls.account.tokens.index,
            icon: KeyRound,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset" className="border-r-0">
            <SidebarHeader className="relative overflow-hidden">
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-x-0 top-0 h-24 brand-gradient [mask-image:linear-gradient(to_bottom,black,transparent)] opacity-[0.09]"
                />

                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="hover:bg-transparent"
                        >
                            <Link href={urls.dashboard} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-2">
                <NavMain items={platformNavItems} label="Platform" />
                <NavMain items={messagingNavItems} label="Messaging" />
                {billingNavItems.length > 0 && (
                    <NavMain items={billingNavItems} label="Billing" />
                )}
                {(canReviewCampaigns || canReviewSenderIds) && (
                    <NavMain
                        items={[
                            {
                                title: 'Approvals',
                                href: urls.approvals.index,
                                icon: ClipboardCheck,
                            },
                        ]}
                        label="Review"
                    />
                )}
                {isPlatformUser && (canManageClients || canReviewClients) && (
                    <NavMain
                        items={[
                            ...(canManageClients
                                ? [
                                      {
                                          title: 'Clients',
                                          href: clients.index.url(),
                                          icon: Users,
                                      },
                                  ]
                                : []),
                            ...(canReviewClients
                                ? [
                                      {
                                          title: 'Registrations',
                                          href: registrations.index.url(),
                                          icon: UserRoundCog,
                                      },
                                  ]
                                : []),
                            ...(canManageClients
                                ? [
                                      {
                                          title: 'Pending',
                                          href: clients.index.url({
                                              query: { status: 'pending' },
                                          }),
                                          icon: ClipboardCheck,
                                      },
                                      {
                                          title: 'Active',
                                          href: clients.index.url({
                                              query: { status: 'active' },
                                          }),
                                          icon: Users,
                                      },
                                      {
                                          title: 'Suspended',
                                          href: clients.index.url({
                                              query: { status: 'suspended' },
                                          }),
                                          icon: ShieldCheck,
                                      },
                                      {
                                          title: 'Documents',
                                          href: clients.index.url({
                                              query: { view: 'documents' },
                                          }),
                                          icon: FileText,
                                      },
                                      {
                                          title: 'Users',
                                          href: urls.admin.users.index,
                                          icon: UsersRound,
                                      },
                                      {
                                          title: 'Service entitlements',
                                          href: clients.index.url({
                                              query: { view: 'services' },
                                          }),
                                          icon: PackagePlus,
                                      },
                                  ]
                                : []),
                        ]}
                        label="Clients"
                    />
                )}
                {isPlatformUser && canManageUsers && (
                    <NavMain items={identityNavItems} label="Users" />
                )}
                {canViewQueues && (
                    <NavMain items={operationsNavItems} label="Operations" />
                )}
                {canViewReports && !isPlatformUser && (
                    <NavMain items={reportsNavItems} label="Reports" />
                )}
                {canViewReports && isPlatformUser && (
                    <NavMain items={platformReportsNavItems} label="Reports" />
                )}
                <NavMain items={accountNavItems} label="Account" />
            </SidebarContent>

            <SidebarFooter className="gap-2">
                <div className="sheen-sweep mx-1 rounded-xl brand-gradient-soft p-3 ring-1 ring-brand/15 group-data-[collapsible=icon]:hidden">
                    <p className="flex items-center gap-1.5 text-xs font-semibold">
                        <Sparkles className="size-3.5 text-brand" />
                        How approvals work
                    </p>
                    <p className="mt-1 text-[11px] leading-snug text-muted-foreground">
                        Submitting a campaign reserves credit. Launching
                        captures it and starts delivery.
                    </p>
                </div>

                <NavUser />

                <PoweredBy className="px-2 pb-1 text-center group-data-[collapsible=icon]:hidden" />
            </SidebarFooter>
        </Sidebar>
    );
}
