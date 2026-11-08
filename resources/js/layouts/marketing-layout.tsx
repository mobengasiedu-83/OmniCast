import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { ReactNode } from 'react';
import BrandMark from '@/components/brand-mark';
import PoweredBy from '@/components/powered-by';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { apiDocs, dashboard, home, login, pricing, userManual } from '@/routes';
import { create as clientSignup } from '@/routes/client-registration';

/**
 * The public chrome around the landing and price list pages: brand header with
 * the section links, and the small print in the footer.
 */
export default function MarketingLayout({ children }: { children: ReactNode }) {
    const { auth, name } = usePage().props;
    const currentPath = usePage().url.split('?')[0].replace(/\/+$/, '');

    const isActive = (href: string): boolean =>
        currentPath === href.replace(/\/+$/, '');

    const links = [
        { label: 'Products', href: home(), active: isActive(home.url()) },
        { label: 'Pricing', href: pricing(), active: isActive(pricing.url()) },
        {
            label: 'User Manual',
            href: userManual(),
            active: isActive(userManual.url()),
        },
        { label: 'API Docs', href: apiDocs(), active: isActive(apiDocs.url()) },
    ];

    return (
        <div className="relative min-h-svh overflow-hidden">
            <div className="pointer-events-none absolute inset-0 -z-10 mesh-backdrop opacity-70" />
            <div className="pointer-events-none absolute inset-0 -z-10 grid-pattern [mask-image:radial-gradient(ellipse_at_top,black,transparent_72%)] opacity-30" />

            <header className="mx-auto flex w-full max-w-6xl animate-fade-in-down items-center justify-between gap-4 px-6 py-5">
                <Link href={home()} className="flex items-center gap-2.5">
                    <BrandMark className="size-9" ambient />
                    <span className="text-sm font-semibold tracking-tight">
                        {name}
                    </span>
                </Link>

                <div className="flex items-center gap-1 sm:gap-2">
                    <nav className="mr-1 hidden items-center gap-1 md:mr-3 md:flex">
                        {links.map((link) => (
                            <Button
                                key={link.label}
                                variant="ghost"
                                size="sm"
                                asChild
                            >
                                <Link
                                    href={link.href}
                                    className={cn(
                                        !link.active &&
                                            'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {link.label}
                                </Link>
                            </Button>
                        ))}
                    </nav>

                    {auth.user ? (
                        <Button asChild>
                            <Link href={dashboard()}>
                                Open dashboard
                                <ArrowRight />
                            </Link>
                        </Button>
                    ) : (
                        <>
                            <Button variant="ghost" asChild>
                                <Link href={login()}>Log in</Link>
                            </Button>
                            <Button asChild>
                                <Link href={clientSignup()}>
                                    Get started
                                    <ArrowRight />
                                </Link>
                            </Button>
                        </>
                    )}
                </div>
            </header>

            <nav
                aria-label="Sections"
                className="mx-auto -mt-1 flex w-full max-w-6xl gap-1 overflow-x-auto px-4 pb-2 md:hidden"
            >
                {links.map((link) => (
                    <Link
                        key={link.label}
                        href={link.href}
                        className={cn(
                            'shrink-0 rounded-full px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors',
                            link.active
                                ? 'bg-brand-soft text-accent-foreground ring-1 ring-brand/20'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                        )}
                    >
                        {link.label}
                    </Link>
                ))}
            </nav>

            <main className="mx-auto w-full max-w-6xl px-6 pb-20">
                {children}
            </main>

            <footer className="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-3 border-t border-border/60 px-6 py-6 text-xs text-muted-foreground">
                <span>
                    © {new Date().getFullYear()} {name}
                </span>
                <div className="flex flex-wrap items-center gap-4">
                    <nav className="flex flex-wrap items-center gap-4">
                        {links.map((link) => (
                            <Link
                                key={link.label}
                                href={link.href}
                                className="hover:text-foreground"
                            >
                                {link.label}
                            </Link>
                        ))}
                    </nav>
                    <PoweredBy />
                    <span className="hidden lg:inline">
                        SMS, voice and email delivery, accounted for.
                    </span>
                </div>
            </footer>
        </div>
    );
}
