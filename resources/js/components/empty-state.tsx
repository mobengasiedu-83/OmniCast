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
            <span className="brand-gradient-soft ring-brand/15 flex size-14 items-center justify-center rounded-2xl ring-1">
                {Icon && <Icon className="text-brand size-6" />}
            </span>

            <div className="space-y-1">
                <p className="font-medium">{title}</p>

                {description && (
                    <p className="text-muted-foreground mx-auto max-w-sm text-sm">
                        {description}
                    </p>
                )}
            </div>

            {action}
        </div>
    );
}
