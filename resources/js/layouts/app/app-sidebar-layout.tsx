import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import PageTransition from '@/components/page-transition';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />

            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />

                <div className="relative flex-1">
                    <div
                        aria-hidden="true"
                        className="brand-gradient-soft pointer-events-none absolute inset-x-0 top-0 h-48 opacity-50 [mask-image:linear-gradient(to_bottom,black,transparent)]"
                    />

                    <PageTransition>
                        <div className="relative mx-auto w-full max-w-[1500px] px-4 py-5 md:px-6 md:py-6">
                            {children}
                        </div>
                    </PageTransition>
                </div>
            </AppContent>
        </AppShell>
    );
}

