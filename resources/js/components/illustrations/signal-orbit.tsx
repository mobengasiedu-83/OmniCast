import { cn } from '@/lib/utils';

/**
 * Concentric broadcast artwork that anchors the auth panel and landing hero.
 */
export default function SignalOrbit({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 320 320"
            fill="none"
            aria-hidden="true"
            className={cn('text-brand', className)}
        >
            <circle
                cx="160"
                cy="160"
                r="152"
                stroke="currentColor"
                strokeOpacity="0.12"
            />
            <circle
                cx="160"
                cy="160"
                r="114"
                stroke="currentColor"
                strokeOpacity="0.16"
                strokeDasharray="4 8"
            />
            <circle
                cx="160"
                cy="160"
                r="76"
                stroke="currentColor"
                strokeOpacity="0.2"
            />
            <circle cx="160" cy="160" r="9" fill="currentColor" />
            <circle
                cx="160"
                cy="160"
                r="9"
                fill="currentColor"
                className="animate-ping-soft"
                style={{ transformOrigin: '160px 160px' }}
            />
            <path
                d="M198 122a53 53 0 0 1 0 76"
                stroke="currentColor"
                strokeWidth="7"
                strokeLinecap="round"
                strokeOpacity="0.85"
            />
            <path
                d="M220 100a84 84 0 0 1 0 120"
                stroke="currentColor"
                strokeWidth="7"
                strokeLinecap="round"
                strokeOpacity="0.55"
            />
            <path
                d="M242 78a115 115 0 0 1 0 164"
                stroke="currentColor"
                strokeWidth="7"
                strokeLinecap="round"
                strokeOpacity="0.3"
            />
            <circle
                cx="160"
                cy="8"
                r="5"
                fill="currentColor"
                fillOpacity="0.7"
            />
            <circle
                cx="312"
                cy="160"
                r="4"
                fill="currentColor"
                fillOpacity="0.5"
            />
            <circle
                cx="86"
                cy="234"
                r="3"
                fill="currentColor"
                fillOpacity="0.4"
            />
        </svg>
    );
}
