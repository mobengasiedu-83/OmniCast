import { cn } from '@/lib/utils';

/**
 * The vendor credit for the platform, linked back to the SysUp360 site.
 *
 * It is rendered as a plain anchor rather than an Inertia `<Link>` because the
 * destination is outside the application.
 */
export default function PoweredBy({ className }: { className?: string }) {
    return (
        <p
            className={cn(
                'text-[11px] leading-relaxed text-muted-foreground',
                className,
            )}
        >
            Powered by{' '}
            <a
                href="https://sysup360.com"
                target="_blank"
                rel="noopener noreferrer"
                className="font-semibold underline-offset-4 transition-colors hover:text-foreground hover:underline"
            >
                SysUp360
            </a>
        </p>
    );
}
