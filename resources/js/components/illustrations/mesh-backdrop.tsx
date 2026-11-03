import { cn } from '@/lib/utils';

/**
 * Layered brand mesh used behind the authentication panel and the landing
 * hero. Pure CSS so it stays crisp at any density and needs no image request.
 */
export default function MeshBackdrop({ className }: { className?: string }) {
    return (
        <div
            aria-hidden="true"
            className={cn(
                'pointer-events-none absolute inset-0 overflow-hidden',
                className,
            )}
        >
            <div className="mesh-backdrop absolute inset-0" />
            <div className="grid-pattern absolute inset-0 opacity-40 [mask-image:radial-gradient(ellipse_at_center,black,transparent_75%)]" />
            <div className="bg-brand/30 animate-glow absolute -top-24 -left-20 size-72 rounded-full blur-3xl" />
            <div className="bg-signal/25 animate-glow absolute top-1/3 -right-24 size-80 rounded-full blur-3xl [animation-delay:1.5s]" />
            <div className="bg-brand-via/20 animate-glow absolute -bottom-28 left-1/3 size-72 rounded-full blur-3xl [animation-delay:3s]" />
        </div>
    );
}
