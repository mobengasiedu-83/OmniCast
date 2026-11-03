import { Link, usePage } from '@inertiajs/react';
import {
    KeyRound,
    LayoutDashboard,
    MailPlus,
    Megaphone,
    MonitorSmartphone,
    ScrollText,
    ShieldCheck,
    UserRoundCog,
    Users,
    UsersRound,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
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
    },
];

const identityNavItems: NavItem[] = [
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

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;

    const isPlatformUser = auth?.isPlatformUser ?? false;
    const permissions = auth?.permissions ?? [];
    const canManageUsers = permissions.includes('users.manage');
    const isClientUser = !isPlatformUser && (auth?.clientId ?? null) !== null;

    const accountNavItems: NavItem[] = [
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
            <SidebarHeader>
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
                {isPlatformUser && canManageUsers && (
                    <NavMain items={identityNavItems} label="Users" />
                )}
                <NavMain items={accountNavItems} label="Account" />
            </SidebarContent>

            <SidebarFooter className="gap-2">
                <div className="brand-gradient-soft ring-brand/15 mx-1 rounded-xl p-3 ring-1 group-data-[collapsible=icon]:hidden">
                    <p className="text-xs font-semibold">How approvals work</p>
                    <p className="text-muted-foreground mt-1 text-[11px] leading-snug">
                        Submitting a campaign reserves credit. Launching
                        captures it and starts delivery.
                    </p>
                </div>

                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

