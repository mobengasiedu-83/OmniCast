import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * The single page heading pattern used across the domain screens: an optional
 * gradient icon, the title with an optional status badge, a supporting line and
 * right-aligned actions.
 */
export default function PageHeader({
    title,
    description,
    icon: Icon,
    badge,
    actions,
    className,
}: {
    title: string;
    description?: string;
    icon?: LucideIcon;
    badge?: ReactNode;
    actions?: ReactNode;
    className?: string;
}) {
    return (
        <header
            className={cn(
                'flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between',
                className,
            )}
        >
            <div className="flex items-start gap-3.5">
                {Icon && (
                    <span className="hidden size-11 shrink-0 items-center justify-center rounded-xl brand-gradient text-primary-foreground shadow-md shadow-brand/25 sm:flex">
                        <Icon className="size-5" />
                    </span>
                )}

                <div className="space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="text-xl font-semibold tracking-tight sm:text-2xl">
                            {title}
                        </h1>
                        {badge}
                    </div>

                    {description && (
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
            </div>

            {actions && (
                <div className="flex flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </header>
    );
}
