import type { SVGProps } from 'react';
import { cn } from '@/lib/utils';

/**
 * The OmniCast glyph: a broadcast origin sending out signal arcs.
 *
 * It is drawn with `currentColor` so it can sit on the brand gradient or on a
 * plain surface without shipping a second image asset.
 */
export function BrandGlyph(props: SVGProps<SVGSVGElement>) {
    return (
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" {...props}>
            <circle cx="5.4" cy="12" r="2.4" fill="currentColor" />
            <path
                d="M10.4 6.7a7.4 7.4 0 0 1 0 10.6"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                opacity="0.9"
            />
            <path
                d="M14.6 3.5a12 12 0 0 1 0 17"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                opacity="0.6"
            />
            <path
                d="M18.6 0.9a16.6 16.6 0 0 1 0 22.2"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                opacity="0.35"
            />
        </svg>
    );
}

export default function BrandMark({
    className,
    glyphClassName,
    ambient = false,
}: {
    className?: string;
    glyphClassName?: string;
    /** Adds a slow warm halo; use it where the mark has room to breathe. */
    ambient?: boolean;
}) {
    return (
        <span
            className={cn(
                'relative isolate inline-flex shrink-0 rounded-xl',
                className,
            )}
        >
            {ambient && (
                <span
                    aria-hidden="true"
                    className="absolute -inset-2 -z-10 animate-glow rounded-full brand-gradient opacity-45 blur-lg"
                />
            )}

            <span
                className={cn(
                    'relative flex size-full items-center justify-center rounded-[inherit] brand-gradient text-primary-foreground shadow-sm ring-1 shadow-brand/30 ring-white/25',
                )}
            >
                <BrandGlyph className={cn('size-5', glyphClassName)} />
            </span>
        </span>
    );
}
