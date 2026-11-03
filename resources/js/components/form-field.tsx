import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/**
 * A labelled form control paired with its validation message.
 */
export default function FormField({
    label,
    htmlFor,
    error,
    children,
    className,
}: {
    label: string;
    htmlFor?: string;
    error?: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('grid gap-2', className)}>
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
