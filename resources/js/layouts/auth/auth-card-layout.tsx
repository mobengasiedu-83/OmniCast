import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import BrandMark from '@/components/brand-mark';
import PoweredBy from '@/components/powered-by';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';

export default function AuthCardLayout({
    children,
    title,
    description,
}: PropsWithChildren<{
    name?: string;
    title?: string;
    description?: string;
}>) {
    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div className="pointer-events-none absolute inset-0 -z-10 animate-fade-in mesh-backdrop opacity-60" />

            <div className="flex w-full max-w-md animate-fade-in-up flex-col gap-6">
                <Link
                    href={home()}
                    className="flex items-center gap-2 self-center font-medium"
                    aria-label={title}
                >
                    <BrandMark className="size-10" glyphClassName="size-5" />
                </Link>

                <Card className="overflow-hidden rounded-2xl">
                    <CardHeader className="border-b border-border/60 bg-muted/40 px-8 pt-7 pb-5 text-center">
                        <CardTitle className="text-xl">{title}</CardTitle>
                        <CardDescription>{description}</CardDescription>
                    </CardHeader>
                    <CardContent className="px-8 py-7">{children}</CardContent>
                </Card>

                <PoweredBy className="self-center" />
            </div>
        </div>
    );
}
