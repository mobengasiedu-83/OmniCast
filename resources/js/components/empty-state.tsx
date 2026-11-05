import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * The shared "nothing here yet" panel for tables, lists and cards.
 */
export default function EmptyState({
    icon: Icon,
    title,
    description,
    action,
    className,
}: {
    icon?: LucideIcon;
    title: string;
    description?: string;
    action?: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center gap-3 px-6 py-12 text-center',
                className,
            )}
        >
            <span className="relative flex size-14 animate-pop items-center justify-center rounded-2xl brand-gradient-soft ring-1 ring-brand/15">
                <span
                    aria-hidden="true"
                    className="absolute inset-0 animate-ping-soft rounded-2xl border border-brand/30"
                />
                {Icon && <Icon className="size-6 text-brand" />}
            </span>

            <div className="space-y-1">
                <p className="font-medium">{title}</p>

                {description && (
                    <p className="mx-auto max-w-sm text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>

            {action}
        </div>
    );
}
