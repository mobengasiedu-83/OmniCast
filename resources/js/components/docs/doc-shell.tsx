import { Head, Link } from '@inertiajs/react';
import { ArrowRight, BookOpen, Code2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { apiDocs, userManual } from '@/routes';

/**
 * The anchor a document's "On this page" rail links to.
 */
export type DocSectionLink = {
    id: string;
    label: string;
};

type DocShellProps = {
    kind: 'manual' | 'api';
    title: string;
    eyebrow: string;
    intro: ReactNode;
    sections: DocSectionLink[];
    children: ReactNode;
};

/**
 * The frame both public documents share: a hero, a sticky table of contents
 * and the chapters themselves.
 *
 * The rail is a plain anchor list rather than a router link, so a shared URL
 * with `#chapter` opens on the right heading and the browser's own back button
 * still works.
 */
export default function DocShell({
    kind,
    title,
    eyebrow,
    intro,
    sections,
    children,
}: DocShellProps) {
    const isManual = kind === 'manual';
    const crossLink = isManual
        ? { label: 'Read the API documentation', href: apiDocs() }
        : { label: 'Read the user manual', href: userManual() };

    return (
        <>
            <Head title={title} />

            <section className="py-10 lg:py-14">
                <div className="max-w-3xl animate-fade-in-up space-y-5">
                    <span className="inline-flex items-center gap-2 rounded-full bg-brand-soft px-3 py-1 text-xs font-medium text-accent-foreground ring-1 ring-brand/20">
                        {isManual ? (
                            <BookOpen className="size-3.5" />
                        ) : (
                            <Code2 className="size-3.5" />
                        )}
                        {eyebrow}
                    </span>

                    <h1 className="text-4xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-5xl">
                        {title}
                    </h1>

                    <div className="max-w-2xl text-base leading-relaxed text-muted-foreground">
                        {intro}
                    </div>
                </div>
            </section>

            <div className="grid gap-10 lg:grid-cols-[216px_minmax(0,1fr)] lg:gap-14">
                <aside className="lg:sticky lg:top-6 lg:self-start">
                    <nav
                        aria-label="On this page"
                        className="rounded-xl border border-border/70 bg-card/70 p-4 backdrop-blur"
                    >
                        <p className="mb-2 flex items-center gap-1.5 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                            On this page
                        </p>
                        <ol className="max-h-[60vh] space-y-1 overflow-y-auto pr-1 text-sm">
                            {sections.map((section, index) => (
                                <li key={section.id}>
                                    <a
                                        href={`#${section.id}`}
                                        className="flex gap-2 rounded-md px-2 py-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                    >
                                        <span className="font-mono text-[11px] text-muted-foreground/60">
                                            {String(index + 1).padStart(2, '0')}
                                        </span>
                                        {section.label}
                                    </a>
                                </li>
                            ))}
                        </ol>
                    </nav>
                </aside>

                <article className="min-w-0 space-y-10 pb-4">
                    {children}
                </article>
            </div>

            <section className="mt-12 flex flex-col gap-4 rounded-2xl border border-border/70 bg-card p-6 sm:flex-row sm:items-center sm:justify-between">
                <div className="space-y-1">
                    <p className="font-semibold">
                        {isManual
                            ? 'Building an integration?'
                            : 'Still learning the screens?'}
                    </p>
                    <p className="text-sm leading-relaxed text-muted-foreground">
                        {isManual
                            ? 'The HTTP API, webhooks and the SMPP listener are documented endpoint by endpoint, with request and response samples.'
                            : 'The manual covers client workflows and the platform staff console, including contacts, campaigns, approvals, billing, support and operations.'}
                    </p>
                </div>
                <Button variant="outline" asChild>
                    <Link href={crossLink.href}>
                        {crossLink.label}
                        <ArrowRight />
                    </Link>
                </Button>
            </section>
        </>
    );
}
