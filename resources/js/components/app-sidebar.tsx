import { Link, usePage } from '@inertiajs/react';
import {
    Banknote,
    BarChart3,
    Bell,
    Blocks,
    BookOpen,
    ChartLine,
    ClipboardCheck,
    Code2,
    CreditCard,
    Download,
    FileText,
    Fingerprint,
    Gauge,
    Inbox,
    KeyRound,
    LayoutDashboard,
    LayoutTemplate,
    LibraryBig,
    LifeBuoy,
    Mail,
    MailPlus,
    Megaphone,
    MessageSquareText,
    MonitorSmartphone,
    PackageCheck,
    PackagePlus,
    Layers3,
    PhoneCall,
    PlayCircle,
    Plug,
    Radio,
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
    Webhook,
    Workflow,
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
import systemRoutes from '@/routes/admin/system';
import type { Auth, ModuleAvailability, ModuleKey, NavItem } from '@/types';

const platformNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: urls.dashboard,
        icon: LayoutDashboard,
    },
];

/**
 * The public documentation, opened in a new tab so a signed-in client keeps the
 * screen they were working on. Every operator carries these links — the manual
 * and the API reference are the same pages the marketing site serves.
 */
const documentationNavItems: NavItem[] = [
    {
        title: 'User manual',
        href: urls.documentation.userManual,
        icon: BookOpen,
        external: true,
    },
    {
        title: 'API docs',
        href: urls.documentation.apiDocs,
        icon: Code2,
        external: true,
    },
];

const messagingNavItems = (modules: ModuleAvailability): NavItem[] => {
    const enabled = (module: ModuleKey): boolean => modules?.[module] ?? true;
    const anyModule = enabled('sms') || enabled('voice') || enabled('email');
    const items: NavItem[] = [
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
                ...(enabled('sms')
                    ? [
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
                      ]
                    : []),
            ],
        },
        ...(anyModule
            ? [
                  {
                      title: 'Sender IDs',
                      href: urls.senderIds.index,
                      icon: Fingerprint,
                  },
              ]
            : []),
        ...(enabled('voice')
            ? [
                  {
                      title: 'Voice calls',
                      href: urls.reports.voice,
                      icon: PhoneCall,
                  },
              ]
            : []),
        ...(enabled('sms')
            ? [
                  {
                      title: 'SMS messages',
                      href: urls.reports.sms,
                      icon: MessageSquareText,
                  },
              ]
            : []),
        ...(enabled('email')
            ? [
                  {
                      title: 'Email messages',
                      href: urls.reports.email,
                      icon: Mail,
                  },
              ]
            : []),
    ];

    return items.filter((item) => item.title !== 'Contacts' || anyModule);
};

const identityNavItems: NavItem[] = [
    {
        title: 'Global settings',
        href: urls.admin.settings.index,
        icon: Settings,
    },
    {
        title: 'Module availability',
        href: urls.admin.settings.modules,
        icon: Blocks,
    },
    {
        title: 'Integrations',
        href: urls.admin.integrations.index,
        icon: Plug,
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
        title: 'System health',
        href: systemRoutes.index.url(),
        icon: ServerCog,
    },
    {
        title: 'Delivery queues',
        href: urls.admin.queues.index,
        icon: Gauge,
    },
    {
        title: 'Webhooks',
        href: urls.admin.webhooks.index,
        icon: Webhook,
    },
    {
        title: 'SMPP',
        href: urls.admin.smpp.index,
        icon: Radio,
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
    const { auth, modules } = usePage<{
        auth: Auth;
        modules: ModuleAvailability;
    }>().props;

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
    const canViewAutomations = permissions.includes('automations.view');
    const canViewAnnouncements =
        isPlatformUser && permissions.includes('announcements.view');
    const canViewLegal = isPlatformUser && permissions.includes('legal.view');
    const canManageLegal = isPlatformUser && permissions.includes('legal.manage');
    const canViewSupport =
        isPlatformUser && permissions.includes('support.view');
    const canViewTemplates = permissions.includes('templates.view');
    const canManageTemplates =
        isPlatformUser && permissions.includes('templates.manage');
    const canApproveTemplates = permissions.includes('templates.approve');
    const isClientUser = !isPlatformUser && (auth?.clientId ?? null) !== null;

    /**
     * The library every operator browses, plus the platform's catalogue,
     * review queue and usage report for the team that maintains it.
     */
    const templateNavItems: NavItem[] = [
        ...(canViewTemplates
            ? [
                  {
                      title: 'Templates',
                      href: urls.templates.index,
                      icon: LayoutTemplate,
                  },
              ]
            : []),
        ...(canManageTemplates
            ? [
                  {
                      title: 'Catalogue',
                      href: urls.admin.templates.index,
                      icon: LibraryBig,
                  },
                  {
                      title: 'Template usage',
                      href: urls.admin.templateUsage.index,
                      icon: BarChart3,
                  },
              ]
            : []),
        ...(canApproveTemplates
            ? [
                  {
                      title: 'Template reviews',
                      href: urls.admin.templateApprovals.index,
                      icon: ClipboardCheck,
                  },
              ]
            : []),
    ];

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
        {
            title: 'Notifications',
            href: urls.notifications.index,
            icon: Bell,
        },
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
        ...(isClientUser
            ? [
                  {
                      title: 'Webhooks',
                      href: urls.account.webhooks.index,
                      icon: Webhook,
                  },
              ]
            : []),
    ];

    /**
     * The platform's own messaging centre: compose an announcement once and
     * deliver it to an audience of accounts.
     */
    const announcementNavItems: NavItem[] = canViewAnnouncements
        ? [
              {
                  title: 'Announcements',
                  href: urls.admin.announcements.index,
                  icon: Megaphone,
              },
          ]
        : [];

    /**
     * The tenant's workflow builder and the run history behind it.
     */
    const automationNavItems: NavItem[] = canViewAutomations
        ? [
              {
                  title: 'Workflows',
                  href: urls.automations.index,
                  icon: Workflow,
              },
              {
                  title: 'Automation runs',
                  href: urls.automations.runs.index,
                  icon: PlayCircle,
              },
          ]
        : [];

    /**
     * A client's own support menu: one place to open a ticket, follow it, read the
     * library and find a person. It carries no permission check because the
     * policy scopes every screen to the requester's own organisation.
     */
    const supportNavItems: NavItem[] = isClientUser
        ? [
              {
                  title: 'Support overview',
                  href: urls.support.index,
                  icon: LifeBuoy,
              },
              {
                  title: 'Create ticket',
                  href: urls.support.tickets.create,
                  icon: MessageSquareText,
              },
              {
                  title: 'My tickets',
                  href: urls.support.tickets.index,
                  icon: Inbox,
              },
              {
                  title: 'Knowledge base',
                  href: urls.support.knowledgeBase.index,
                  icon: BookOpen,
              },
              {
                  title: 'Contact support',
                  href: urls.support.contact,
                  icon: PhoneCall,
              },
          ]
        : [];

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
                <NavMain items={messagingNavItems(modules)} label="Messaging" />
                {templateNavItems.length > 0 && (
                    <NavMain items={templateNavItems} label="Templates" />
                )}
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
                {announcementNavItems.length > 0 && (
                    <NavMain
                        items={announcementNavItems}
                        label="Messaging centre"
                    />
                )}
                {canViewLegal && (
                    <NavMain
                        items={[
                            {
                                title: 'Legal documents',
                                href: urls.admin.legal.documents.index,
                                icon: FileText,
                            },
                            {
                                title: 'Document versions',
                                href: urls.admin.legal.versions.index,
                                icon: Layers3,
                            },
                            {
                                title: 'Acceptance records',
                                href: urls.admin.legal.acceptances.index,
                                icon: ScrollText,
                            },
                            {
                                title: 'DPA management',
                                href: urls.admin.legal.dpas.index,
                                icon: ShieldCheck,
                            },
                            {
                                title: 'Compliance settings',
                                href: urls.admin.legal.complianceSettings,
                                icon: Settings,
                            },
                            {
                                title: 'Policy notifications',
                                href: urls.admin.legal.notifications,
                                icon: Bell,
                            },
                            {
                                title: 'Compliance reports',
                                href: urls.admin.legal.reports,
                                icon: BarChart3,
                            },
                            {
                                title: 'Data retention',
                                href: urls.admin.legal.privacyRetention,
                                icon: Settings,
                            },
                            ...(canManageLegal
                                ? [
                                      {
                                          title: 'Privacy requests',
                                          href: urls.admin.legal.privacyRequests.index,
                                          icon: ShieldCheck,
                                      },
                                  ]
                                : []),
                        ]}
                        label="Legal & compliance"
                    />
                )}
                {canViewSupport && (
                    <NavMain
                        items={[
                            {
                                title: 'Contact enquiries',
                                href: urls.admin.support.enquiries.index,
                                icon: Inbox,
                            },
                            {
                                title: 'Support tickets',
                                href: urls.admin.support.tickets.index,
                                icon: LifeBuoy,
                            },
                            {
                                title: 'Ticket categories',
                                href: urls.admin.support.categories.index,
                                icon: Tags,
                            },
                            {
                                title: 'Support teams',
                                href: urls.admin.support.teams.index,
                                icon: UsersRound,
                            },
                            {
                                title: 'Email notifications',
                                href: urls.admin.support.notifications.index,
                                icon: Bell,
                            },
                            {
                                title: 'Knowledge base',
                                href: urls.admin.support.knowledgeBase.index,
                                icon: LibraryBig,
                            },
                            {
                                title: 'Support reports',
                                href: urls.admin.support.reports.index,
                                icon: BarChart3,
                            },
                        ]}
                        label="Support & enquiries"
                    />
                )}
                {supportNavItems.length > 0 && (
                    <NavMain items={supportNavItems} label="Support" />
                )}
                {canViewReports && !isPlatformUser && (
                    <NavMain items={reportsNavItems} label="Reports" />
                )}
                {automationNavItems.length > 0 && (
                    <NavMain items={automationNavItems} label="Automation" />
                )}
                {canViewReports && isPlatformUser && (
                    <NavMain items={platformReportsNavItems} label="Reports" />
                )}
                <NavMain items={accountNavItems} label="Account" />
                <NavMain items={documentationNavItems} label="Documentation" />
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
