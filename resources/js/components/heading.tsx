import type { LucideIcon } from 'lucide-react';

export default function Heading({
    title,
    description,
    icon: Icon,
    variant = 'default',
}: {
    title: string;
    description?: string;
    icon?: LucideIcon;
    variant?: 'default' | 'small';
}) {
    return (
        <header className={variant === 'small' ? '' : 'mb-8 space-y-0.5'}>
            <div className="flex items-center gap-2.5">
                {Icon && (
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-xl brand-gradient text-primary-foreground shadow-sm shadow-brand/25">
                        <Icon className="size-4" />
                    </span>
                )}

                <h2
                    className={
                        variant === 'small'
                            ? 'mb-0.5 text-base font-medium'
                            : 'text-xl font-semibold tracking-tight'
                    }
                >
                    {title}
                </h2>
            </div>
            {description && (
                <p className="text-sm text-muted-foreground">{description}</p>
            )}
        </header>
    );
}
