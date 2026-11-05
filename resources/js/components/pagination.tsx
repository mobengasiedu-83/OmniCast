import { Link } from '@inertiajs/react';
import type { Paginated } from '@/types';

/**
 * Renders the Laravel paginator links for a paginated listing.
 */
export default function Pagination({
    paginator,
    noun = 'records',
}: {
    paginator: Paginated<unknown>;
    noun?: string;
}) {
    if (paginator.total === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-xs text-muted-foreground">
                Showing {paginator.from ?? 0}–{paginator.to ?? 0} of{' '}
                {paginator.total} {noun}
            </p>
            <div className="flex flex-wrap items-center gap-1">
                {paginator.links.map((link, index) => {
                    const label = link.label
                        .replace('&laquo;', '«')
                        .replace('&raquo;', '»');

                    if (link.url === null) {
                        return (
                            <span
                                key={`${label}-${index}`}
                                className="rounded-md border border-transparent px-2.5 py-1 text-xs text-muted-foreground"
                                dangerouslySetInnerHTML={{ __html: label }}
                            />
                        );
                    }

                    return (
                        <Link
                            key={`${label}-${index}`}
                            href={link.url}
                            preserveScroll
                            className={
                                link.active
                                    ? 'rounded-md border border-transparent bg-primary px-2.5 py-1 text-xs text-primary-foreground'
                                    : 'rounded-md border px-2.5 py-1 text-xs transition-colors hover:bg-accent'
                            }
                            dangerouslySetInnerHTML={{ __html: label }}
                        />
                    );
                })}
            </div>
        </div>
    );
}
