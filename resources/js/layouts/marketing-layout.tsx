import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { ReactNode } from 'react';
import BrandMark from '@/components/brand-mark';
import PoweredBy from '@/components/powered-by';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard, home, login, pricing } from '@/routes';
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
        { label: 'Product', href: home(), active: isActive(home.url()) },
        { label: 'Pricing', href: pricing(), active: isActive(pricing.url()) },
    ];

    return (
        <div className="relative min-h-svh overflow-hidden">
            <div className="pointer-events-none absolute inset-0 -z-10 mesh-backdrop opacity-70" />
            <div className="pointer-events-none absolute inset-0 -z-10 grid-pattern [mask-image:radial-gradient(ellipse_at_top,black,transparent_72%)] opacity-30" />

            <header className="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-6 py-5">
                <Link href={home()} className="flex items-center gap-2.5">
                    <BrandMark className="size-9" />
                    <span className="text-sm font-semibold tracking-tight">
                        {name}
                    </span>
                </Link>

                <div className="flex items-center gap-1 sm:gap-2">
                    <nav className="mr-1 hidden items-center gap-1 sm:mr-3 sm:flex">
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

            <main className="mx-auto w-full max-w-6xl px-6 pb-20">
                {children}
            </main>

            <footer className="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-2 border-t border-border/60 px-6 py-6 text-xs text-muted-foreground">
                <span>
                    © {new Date().getFullYear()} {name}
                </span>
                <div className="flex items-center gap-4">
                    <PoweredBy />
                    <Link href={pricing()} className="hover:text-foreground">
                        Pricing
                    </Link>
                    <span>SMS, voice and email delivery, accounted for.</span>
                </div>
            </footer>
        </div>
    );
}
