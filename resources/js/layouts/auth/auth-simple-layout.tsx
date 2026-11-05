import { Link } from '@inertiajs/react';
import BrandMark from '@/components/brand-mark';
import PoweredBy from '@/components/powered-by';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center p-6 md:p-10">
            <div className="mesh-backdrop animate-fade-in pointer-events-none absolute inset-0 -z-10 opacity-60" />

            <div className="animate-fade-in-up w-full max-w-sm space-y-8">
                <div className="flex flex-col items-center gap-4 text-center">
                    <Link href={home()} aria-label={title}>
                        <BrandMark className="size-11" glyphClassName="size-6" />
                    </Link>

                    <div className="space-y-1.5">
                        <h1 className="text-xl font-semibold tracking-tight">
                            {title}
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {description}
                        </p>
                    </div>
                </div>

                {children}
            </div>

            <PoweredBy className="mt-8 text-center" />
        </div>
    );
}

