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
            <div className="absolute inset-0 mesh-backdrop" />
            <div className="absolute inset-0 grid-pattern [mask-image:radial-gradient(ellipse_at_center,black,transparent_75%)] opacity-40" />
            <div className="absolute -top-24 -left-20 size-72 animate-glow rounded-full bg-brand/30 blur-3xl" />
            <div className="absolute top-1/3 -right-24 size-80 animate-drift rounded-full bg-signal/20 blur-3xl" />
            <div className="bg-brand-to/25 absolute -bottom-28 left-1/3 size-72 animate-drift-slow rounded-full blur-3xl" />
        </div>
    );
}
