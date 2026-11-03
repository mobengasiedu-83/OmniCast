import { Link, usePage } from '@inertiajs/react';
import {
    KeyRound,
    MonitorSmartphone,
    Palette,
    ShieldCheck,
    UserRound,
    UsersRound,
} from 'lucide-react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { urls } from '@/lib/urls';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { Auth, NavItem } from '@/types';

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { auth } = usePage<{ auth: Auth }>().props;

    const canManageUsers = (auth?.permissions ?? []).includes('users.manage');
    const isClientUser =
        !(auth?.isPlatformUser ?? false) && (auth?.clientId ?? null) !== null;

    const sidebarNavItems: NavItem[] = [
        {
            title: 'Profile',
            href: edit(),
            icon: UserRound,
        },
        {
            title: 'Security',
            href: editSecurity(),
            icon: ShieldCheck,
        },
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
        {
            title: 'Appearance',
            href: editAppearance(),
            icon: Palette,
        },
    ];

    return (
        <div className="flex flex-col gap-6">
            <Heading
                title="Settings"
                description="Manage your profile and account settings"
            />

            <div className="flex flex-col gap-8 lg:flex-row">
                <aside className="w-full lg:w-52">
                    <nav
                        className="flex flex-col gap-1"
                        aria-label="Settings"
                    >
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn(
                                    'h-9 w-full justify-start gap-2 rounded-lg font-medium',
                                    {
                                        'bg-brand-soft text-accent-foreground hover:bg-brand-soft':
                                            isCurrentOrParentUrl(item.href),
                                    },
                                )}
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon className="size-4" />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="lg:hidden" />

                <div className="min-w-0 flex-1">
                    <section className="max-w-2xl space-y-10">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}

