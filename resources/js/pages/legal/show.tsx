import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { urls } from '@/lib/urls';
import { index, show } from '@/routes/legal';

type LegalDocument = {
    slug: string;
    title: string;
    summary: string | null;
    version: string | null;
    effective_on: string | null;
    published_at: string | null;
    change_summary: string | null;
    html: string;
    url: string;
};

type PageProps = {
    document: LegalDocument;
    others: {
        slug: string;
        title: string;
        summary: string | null;
        version: string | null;
        effective_on: string | null;
        url: string;
    }[];
};

/**
 * One published policy, rendered from the version that is live today.
 *
 * The markdown is converted to HTML on the server and injected here, so the page
 * is complete before React mounts and stays readable with JavaScript disabled.
 * "Download PDF" opens the print-ready copy of the same published version, so the
 * download can never drift from the text the page shows.
 */
export default function LegalShow({ document, others }: PageProps) {
    return (
        <>
            <Head title={document.title} />

            <section className="mx-auto max-w-3xl pt-8">
                <Button
                    variant="ghost"
                    size="sm"
                    className="mb-6 -ml-2"
                    asChild
                >
                    <Link href={index()}>
                        <ArrowLeft />
                        All policies
                    </Link>
                </Button>

                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            {document.title}
                        </h1>
                        <p className="mt-2 text-xs text-muted-foreground">
                            {document.version ?? 'Unversioned'}
                            {document.effective_on
                                ? ` · effective ${document.effective_on}`
                                : ''}
                            {document.published_at
                                ? ` · published ${new Date(document.published_at).toLocaleDateString()}`
                                : ''}
                        </p>
                    </div>

                    <Button variant="outline" size="sm" asChild>
                        <a
                            href={urls.legal.print(document.slug)}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <Download />
                            Download PDF
                        </a>
                    </Button>
                </div>

                {document.change_summary && (
                    <p className="mt-4 rounded-lg border border-border/60 bg-muted/40 px-4 py-3 text-xs text-muted-foreground">
                        What changed in this version: {document.change_summary}
                    </p>
                )}

                <article
                    className="legal-document mt-8"
                    // Rendered from the published version by the server; raw HTML
                    // in the stored markdown is stripped before it gets here.
                    dangerouslySetInnerHTML={{ __html: document.html }}
                />
            </section>

            {others.length > 0 && (
                <section className="mx-auto mt-12 max-w-3xl border-t border-border/60 pt-6">
                    <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Other policies
                    </h2>
                    <div className="mt-3 grid gap-2 sm:grid-cols-2">
                        {others.map((other) => (
                            <Link
                                key={other.slug}
                                href={show.url(other.slug)}
                                className="rounded-lg border border-border/60 px-4 py-3 text-sm transition-colors hover:border-brand/40 hover:bg-card"
                            >
                                <span className="font-medium">
                                    {other.title}
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    {other.version ?? 'Not published'}
                                </span>
                            </Link>
                        ))}
                    </div>
                </section>
            )}
        </>
    );
}
