import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import type { CSSProperties } from 'react';
import { usePage } from '@inertiajs/react';
import type { BreadcrumbItem } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    const { clientBranding } = usePage<{ clientBranding?: { primaryColor?: string | null } }>().props;
    const color = clientBranding?.primaryColor;
    const brandingStyle: CSSProperties & Record<string, string> = color
        ? {
              '--brand': color,
              '--brand-strong': color,
              '--brand-soft': `color-mix(in srgb, ${color} 14%, transparent)`,
          }
        : {};

    return (
        <div style={brandingStyle}>
            <AppLayoutTemplate breadcrumbs={breadcrumbs}>
                {children}
            </AppLayoutTemplate>
        </div>
    );
}
