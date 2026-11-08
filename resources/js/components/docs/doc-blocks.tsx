import {
    AlertTriangle,
    CheckCircle2,
    Copy,
    Info,
    Lightbulb,
    Terminal,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useClipboard } from '@/hooks/use-clipboard';
import { cn } from '@/lib/utils';

/**
 * The building blocks of the User Manual and the API documentation.
 *
 * Both pages are long, so every block here keeps its own spacing and stays
 * readable on a narrow screen: tables scroll, code samples wrap on demand and
 * headings carry the anchor ids the "On this page" list jumps to.
 */

type SectionProps = {
    id: string;
    title: string;
    lead?: ReactNode;
    children: ReactNode;
};

/**
 * One chapter of a document.
 */
export function Section({ id, title, lead, children }: SectionProps) {
    return (
        <section
            id={id}
            className="scroll-mt-28 border-t border-border/60 pt-10 first:border-0 first:pt-0"
        >
            <h2 className="text-xl font-semibold tracking-tight sm:text-2xl">
                {title}
            </h2>

            {lead !== undefined && (
                <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                    {lead}
                </p>
            )}

            <div className="mt-5 space-y-5">{children}</div>
        </section>
    );
}

/**
 * A sub-heading inside a chapter, for the steps of one workflow.
 */
export function SubSection({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <div className="space-y-3">
            <h3 className="text-base font-semibold tracking-tight">{title}</h3>
            {children}
        </div>
    );
}

/**
 * Body copy: the default paragraph voice on a documentation page.
 */
export function Para({ children }: { children: ReactNode }) {
    return (
        <p className="text-sm leading-relaxed text-muted-foreground">
            {children}
        </p>
    );
}

/**
 * A tight bullet list for enumerations that do not deserve a table.
 */
export function Bullets({ items }: { items: ReactNode[] }) {
    return (
        <ul className="space-y-2 text-sm leading-relaxed text-muted-foreground">
            {items.map((item, index) => (
                <li key={index} className="flex gap-2.5">
                    <span
                        aria-hidden
                        className="mt-1.5 size-1.5 shrink-0 rounded-full bg-brand"
                    />
                    <span>{item}</span>
                </li>
            ))}
        </ul>
    );
}

type Step = {
    title: string;
    body: ReactNode;
};

/**
 * A numbered procedure: how a task is actually carried out in the app.
 */
export function Steps({ items }: { items: Step[] }) {
    return (
        <ol className="space-y-4">
            {items.map((item, index) => (
                <li key={item.title} className="flex gap-3">
                    <span className="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-brand-soft text-xs font-semibold text-brand ring-1 ring-brand/20">
                        {index + 1}
                    </span>
                    <div className="space-y-1">
                        <p className="text-sm font-medium">{item.title}</p>
                        <p className="text-sm leading-relaxed text-muted-foreground">
                            {item.body}
                        </p>
                    </div>
                </li>
            ))}
        </ol>
    );
}

type FieldTableProps = {
    columns: string[];
    rows: ReactNode[][];
    caption?: string;
};

/**
 * A reference table: fields, statuses, limits or error codes.
 */
export function FieldTable({ columns, rows, caption }: FieldTableProps) {
    return (
        <div className="overflow-hidden rounded-xl border border-border/70 bg-card">
            <Table>
                <TableHeader>
                    <TableRow>
                        {columns.map((column) => (
                            <TableHead key={column}>{column}</TableHead>
                        ))}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((row, rowIndex) => (
                        <TableRow key={rowIndex}>
                            {row.map((cell, cellIndex) => (
                                <TableCell
                                    key={cellIndex}
                                    className={cn(
                                        'align-top text-sm',
                                        cellIndex === 0 &&
                                            'font-medium whitespace-nowrap',
                                    )}
                                >
                                    {cell}
                                </TableCell>
                            ))}
                        </TableRow>
                    ))}
                </TableBody>
            </Table>

            {caption !== undefined && (
                <p className="border-t border-border/60 px-4 py-2.5 text-xs text-muted-foreground">
                    {caption}
                </p>
            )}
        </div>
    );
}

type CalloutTone = 'note' | 'tip' | 'warning' | 'success';

const CALLOUT_TONES: Record<
    CalloutTone,
    { icon: typeof Info; className: string; iconClassName: string }
> = {
    note: {
        icon: Info,
        className: 'border-info/25 bg-info-soft/60',
        iconClassName: 'text-info',
    },
    tip: {
        icon: Lightbulb,
        className: 'border-brand/25 bg-brand-soft/60',
        iconClassName: 'text-brand',
    },
    warning: {
        icon: AlertTriangle,
        className: 'border-warning/30 bg-warning-soft/60',
        iconClassName: 'text-warning-foreground dark:text-warning',
    },
    success: {
        icon: CheckCircle2,
        className: 'border-success/25 bg-success-soft/60',
        iconClassName: 'text-success',
    },
};

/**
 * A sentence that must not be missed: a caveat, a shortcut or a warning.
 */
export function Callout({
    tone = 'note',
    title,
    children,
}: {
    tone?: CalloutTone;
    title?: string;
    children: ReactNode;
}) {
    const { icon: Icon, className, iconClassName } = CALLOUT_TONES[tone];

    return (
        <div className={cn('flex gap-3 rounded-xl border p-4', className)}>
            <Icon className={cn('mt-0.5 size-4 shrink-0', iconClassName)} />
            <div className="space-y-1 text-sm leading-relaxed">
                {title !== undefined && <p className="font-medium">{title}</p>}
                <div className="text-muted-foreground">{children}</div>
            </div>
        </div>
    );
}

/**
 * A runnable sample: request, response or shell command, with a copy button.
 */
export function CodeBlock({
    code,
    language = 'bash',
    title,
}: {
    code: string;
    language?: string;
    title?: string;
}) {
    const [copied, copy] = useClipboard();

    return (
        <figure className="overflow-hidden rounded-xl border border-border/70 bg-[#0b1220] text-slate-100">
            <figcaption className="flex items-center justify-between gap-3 border-b border-white/10 px-4 py-2">
                <span className="flex items-center gap-2 font-mono text-[11px] tracking-wide text-slate-400 uppercase">
                    <Terminal className="size-3.5" />
                    {title ?? language}
                </span>
                <button
                    type="button"
                    onClick={() => void copy(code)}
                    className="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-[11px] font-medium text-slate-300 transition-colors hover:bg-white/10 hover:text-white"
                >
                    {copied === code ? (
                        <>
                            <CheckCircle2 className="size-3.5" />
                            Copied
                        </>
                    ) : (
                        <>
                            <Copy className="size-3.5" />
                            Copy
                        </>
                    )}
                </button>
            </figcaption>
            <pre className="overflow-x-auto px-4 py-3.5 text-xs leading-relaxed">
                <code>{code}</code>
            </pre>
        </figure>
    );
}

const METHOD_TONES: Record<string, string> = {
    GET: 'border-info/25 bg-info-soft text-info',
    POST: 'border-success/25 bg-success-soft text-success',
    PUT: 'border-warning/30 bg-warning-soft text-warning-foreground dark:text-warning',
    PATCH: 'border-warning/30 bg-warning-soft text-warning-foreground dark:text-warning',
    DELETE: 'border-destructive/25 bg-destructive-soft text-destructive',
};

/**
 * The heading of one documented endpoint: method, path and what it answers.
 */
export function Endpoint({
    method,
    path,
    summary,
    scope,
}: {
    method: string;
    path: string;
    summary: string;
    scope?: string;
}) {
    return (
        <div className="space-y-2 rounded-xl border border-border/70 bg-card p-4">
            <div className="flex flex-wrap items-center gap-2">
                <Badge
                    variant="outline"
                    className={cn(
                        'font-mono tracking-wide',
                        METHOD_TONES[method.toUpperCase()],
                    )}
                >
                    {method.toUpperCase()}
                </Badge>
                <code className="font-mono text-sm break-all">{path}</code>
                {scope !== undefined && (
                    <Badge variant="muted" className="ml-auto font-mono">
                        {scope}
                    </Badge>
                )}
            </div>
            <p className="text-sm leading-relaxed text-muted-foreground">
                {summary}
            </p>
        </div>
    );
}

/**
 * Inline code inside a sentence, e.g. a field name or a status value.
 */
export function Mono({ children }: { children: ReactNode }) {
    return (
        <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-[0.85em] text-foreground">
            {children}
        </code>
    );
}
