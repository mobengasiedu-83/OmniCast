import { Breadcrumbs } from '@/components/breadcrumbs';
import ThemeToggle from '@/components/theme-toggle';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="sticky top-0 z-20 flex h-16 shrink-0 items-center justify-between gap-2 border-b border-border/60 surface-glass px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-14 md:px-6">
            <span
                aria-hidden="true"
                className="absolute inset-x-0 top-0 h-px brand-gradient opacity-70"
            />

            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-y-0 right-0 w-44 dot-pattern [mask-image:linear-gradient(to_left,black,transparent)] opacity-40"
            />

            <div className="relative flex min-w-0 items-center gap-2">
                <SidebarTrigger className="-ml-1 text-muted-foreground hover:text-foreground" />
                <span className="mr-1 hidden h-5 w-px bg-border sm:block" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <div className="relative flex items-center gap-1">
                <ThemeToggle />
            </div>
        </header>
    );
}
