import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { ReactNode } from 'react';
import BrandMark from '@/components/brand-mark';
import PoweredBy from '@/components/powered-by';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { apiDocs, dashboard, home, login, pricing, userManual } from '@/routes';
import { create as clientSignup } from '@/routes/client-registration';
import { show as contactPage } from '@/routes/contact';
import { index as helpCentre } from '@/routes/help';
import { index as legalIndex, show as legalDocument } from '@/routes/legal';

/**
 * The five policy documents, in the order the footer lists them.
 *
 * The slugs are the values of `App\Enums\LegalDocumentType`, which is also the
 * public URL segment; keep the two in step when a document is added.
 */
const LEGAL_LINKS: { label: string; slug: string }[] = [
    { label: 'Terms', slug: 'terms' },
    { label: 'Privacy', slug: 'privacy' },
    { label: 'Acceptable use', slug: 'acceptable-use' },
    { label: 'Refund & wallet', slug: 'refund-wallet' },
    { label: 'Data processing', slug: 'data-processing-agreement' },
];

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
            label: 'Help centre',
            href: helpCentre(),
            active: isActive(helpCentre.url()),
        },
        {
            label: 'User Manual',
            href: userManual(),
            active: isActive(userManual.url()),
        },
        { label: 'API Docs', href: apiDocs(), active: isActive(apiDocs.url()) },
        {
            label: 'Contact',
            href: contactPage(),
            active: isActive(contactPage.url()),
        },
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

            <footer className="mx-auto w-full max-w-6xl border-t border-border/60 px-6 py-8">
                <div className="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-2">
                        <div className="flex items-center gap-2">
                            <BrandMark className="size-6" />
                            <span className="text-sm font-semibold tracking-tight">
                                {name}
                            </span>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            SMS, voice and email delivery, accounted for.
                        </p>
                    </div>

                    <div className="grid gap-6 sm:grid-cols-2">
                        <div>
                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Platform
                            </p>
                            <nav className="mt-2 flex flex-col gap-1.5 text-xs text-muted-foreground">
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
                        </div>

                        <div>
                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Legal
                            </p>
                            <nav className="mt-2 flex flex-col gap-1.5 text-xs text-muted-foreground">
                                {LEGAL_LINKS.map((link) => (
                                    <Link
                                        key={link.slug}
                                        href={legalDocument.url(link.slug)}
                                        className="hover:text-foreground"
                                    >
                                        {link.label}
                                    </Link>
                                ))}
                                <Link
                                    href={legalIndex()}
                                    className="hover:text-foreground"
                                >
                                    All policies
                                </Link>
                            </nav>
                        </div>
                    </div>
                </div>

                <div className="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-border/60 pt-4 text-xs text-muted-foreground">
                    <span>
                        © {new Date().getFullYear()} {name}. All rights reserved.
                    </span>
                    <PoweredBy />
                </div>
            </footer>
        </div>
    );
}
