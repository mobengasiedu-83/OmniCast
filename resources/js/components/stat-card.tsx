import type { LucideIcon } from 'lucide-react';
import type { CSSProperties } from 'react';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export type StatTone =
    | 'brand'
    | 'signal'
    | 'success'
    | 'warning'
    | 'danger'
    | 'neutral';

/**
 * Icon chip and corner glow for each metric tone. The tone carries meaning:
 * brand for volume, success for healthy numbers, warning for anything awaiting
 * attention and danger for failures.
 */
const tones: Record<StatTone, { chip: string; glow: string }> = {
    brand: {
        chip: 'brand-gradient text-primary-foreground shadow-brand/25 shadow-sm',
        glow: 'bg-brand/25',
    },
    signal: {
        chip: 'bg-signal-soft text-signal ring-signal/25 shadow-sm ring-1',
        glow: 'bg-signal/25',
    },
    success: {
        chip: 'bg-success-soft text-success ring-success/25 shadow-sm ring-1',
        glow: 'bg-success/20',
    },
    warning: {
        chip: 'bg-warning-soft text-warning-foreground ring-warning/30 shadow-sm ring-1 dark:text-warning',
        glow: 'bg-warning/25',
    },
    danger: {
        chip: 'bg-destructive-soft text-destructive ring-destructive/25 shadow-sm ring-1',
        glow: 'bg-destructive/20',
    },
    neutral: {
        chip: 'bg-muted text-muted-foreground ring-border shadow-sm ring-1',
        glow: 'bg-muted-foreground/10',
    },
};

export default function StatCard({
    title,
    value,
    description,
    icon: Icon,
    tone = 'brand',
    className,
    style,
}: {
    title: string;
    value: string | number;
    description?: string;
    icon?: LucideIcon;
    tone?: StatTone;
    className?: string;
    style?: CSSProperties;
}) {
    return (
        <Card
            className={cn('card-lift relative gap-0 overflow-hidden py-0', className)}
            style={style}
        >
            <span
                aria-hidden="true"
                className={cn(
                    'pointer-events-none absolute -top-12 -right-10 size-28 rounded-full opacity-70 blur-2xl',
                    tones[tone].glow,
                )}
            />

            <div className="relative space-y-3 p-4">
                <div className="flex items-start justify-between gap-3">
                    <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.14em] uppercase">
                        {title}
                    </p>

                    {Icon && (
                        <span
                            className={cn(
                                'flex size-8 shrink-0 items-center justify-center rounded-lg',
                                tones[tone].chip,
                            )}
                        >
                            <Icon className="size-4" />
                        </span>
                    )}
                </div>

                <div className="space-y-1">
                    <p className="text-2xl font-semibold tracking-tight tabular-nums">
                        {value}
                    </p>

                    {description && (
                        <p className="text-muted-foreground text-xs">
                            {description}
                        </p>
                    )}
                </div>
            </div>
        </Card>
    );
}

