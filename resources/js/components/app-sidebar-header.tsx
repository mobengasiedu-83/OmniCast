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
        <header className="surface-glass sticky top-0 z-20 flex h-16 shrink-0 items-center justify-between gap-2 border-b border-border/60 px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-14 md:px-6">
            <div className="flex min-w-0 items-center gap-2">
                <SidebarTrigger className="text-muted-foreground hover:text-foreground -ml-1" />
                <span className="bg-border mr-1 hidden h-5 w-px sm:block" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <div className="flex items-center gap-1">
                <ThemeToggle />
            </div>
        </header>
    );
}

