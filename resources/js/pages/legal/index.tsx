import { Head, Link } from '@inertiajs/react';
import { ArrowRight, FileText } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { create as clientSignup } from '@/routes/client-registration';
import { show } from '@/routes/legal';

type LegalDocumentSummary = {
    slug: string;
    title: string;
    summary: string | null;
    version: string | null;
    effective_on: string | null;
    url: string;
};

type PageProps = {
    documents: LegalDocumentSummary[];
};

/**
 * The public index of the platform's policies.
 *
 * Every document is a readable page rather than a download, because a customer
 * deciding whether to sign up should not have to open a PDF to find out what
 * they are agreeing to.
 */
export default function LegalIndex({ documents }: PageProps) {
    return (
        <>
            <Head title="Legal" />

            <section className="mx-auto max-w-3xl py-12 text-center">
                <span className="inline-flex items-center gap-2 rounded-full bg-brand-soft px-3 py-1 text-xs font-medium text-accent-foreground ring-1 ring-brand/20">
                    <FileText className="size-3.5" />
                    Policies
                </span>
                <h1 className="mt-5 text-3xl font-semibold tracking-tight sm:text-4xl">
                    Legal documents
                </h1>
                <p className="mt-3 text-sm text-muted-foreground">
                    The terms that govern your account, the data we process, the
                    messages you may send and how the wallet works. Each document
                    states its version and the date that version took effect.
                </p>
            </section>

            <div className="mx-auto grid max-w-3xl gap-3">
                {documents.map((document) => (
                    <Link
                        key={document.slug}
                        href={show.url(document.slug)}
                        className="group flex flex-col gap-2 rounded-xl border border-border/70 bg-card/70 p-5 transition-colors hover:border-brand/40 hover:bg-card"
                    >
                        <div className="flex items-start justify-between gap-4">
                            <h2 className="text-base font-semibold tracking-tight">
                                {document.title}
                            </h2>
                            <ArrowRight className="mt-0.5 size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-brand" />
                        </div>
                        {document.summary && (
                            <p className="text-sm text-muted-foreground">
                                {document.summary}
                            </p>
                        )}
                        <p className="text-xs text-muted-foreground">
                            {document.version ?? 'Not published'}
                            {document.effective_on
                                ? ` · effective ${document.effective_on}`
                                : ''}
                        </p>
                    </Link>
                ))}

                {documents.length === 0 && (
                    <p className="rounded-xl border border-border/70 p-8 text-center text-sm text-muted-foreground">
                        No policies have been published yet.
                    </p>
                )}
            </div>

            <div className="mx-auto mt-10 flex max-w-3xl flex-wrap items-center justify-between gap-3 rounded-xl border border-border/70 bg-muted/40 p-5">
                <p className="text-sm text-muted-foreground">
                    Questions about a document? Ask the platform team before you
                    accept it.
                </p>
                <Button variant="outline" asChild>
                    <Link href={clientSignup()}>
                        Create an account
                        <ArrowRight />
                    </Link>
                </Button>
            </div>
        </>
    );
}
