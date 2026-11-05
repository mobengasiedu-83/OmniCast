import { Link, usePage } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import BrandMark from '@/components/brand-mark';
import MeshBackdrop from '@/components/illustrations/mesh-backdrop';
import SignalOrbit from '@/components/illustrations/signal-orbit';
import PoweredBy from '@/components/powered-by';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

const highlights = [
    'Voice calls and SMS campaigns from one workspace',
    'Credit reserved on submit, captured on launch',
    'DND and delivery tracked per recipient',
];

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="grid min-h-svh lg:grid-cols-[1.05fr_1fr]">
            <aside className="relative hidden overflow-hidden bg-[oklch(0.16_0.03_276)] p-10 text-white lg:flex lg:flex-col lg:justify-between">
                <MeshBackdrop className="opacity-90" />
                <SignalOrbit className="pointer-events-none absolute -right-28 -bottom-32 size-[26rem] text-white/40" />

                <Link
                    href={home()}
                    className="relative z-10 flex items-center gap-3"
                >
                    <BrandMark className="size-10" glyphClassName="size-5" />
                    <span className="space-y-0.5">
                        <span className="block text-sm font-semibold tracking-tight">
                            {name}
                        </span>
                        <span className="block text-[11px] tracking-[0.16em] text-white/50 uppercase">
                            Voice &amp; SMS
                        </span>
                    </span>
                </Link>

                <div className="relative z-10 max-w-md space-y-6">
                    <h2 className="text-3xl leading-tight font-semibold tracking-tight text-balance">
                        Reach every customer, on every channel.
                    </h2>

                    <p className="text-sm leading-relaxed text-white/70">
                        Draft, approve and launch voice and SMS campaigns, then
                        watch delivery and spend land in real time.
                    </p>

                    <ul className="space-y-3">
                        {highlights.map((highlight) => (
                            <li
                                key={highlight}
                                className="flex items-start gap-2.5 text-sm text-white/80"
                            >
                                <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-white/45" />
                                {highlight}
                            </li>
                        ))}
                    </ul>
                </div>

                <div className="relative z-10 space-y-2">
                    <p className="text-[11px] tracking-wide text-white/40">
                        GHS billing · Delivery confirmed per recipient
                    </p>
                    <p className="text-[11px] tracking-wide text-white/40">
                        Powered by{' '}
                        <a
                            href="https://sysup360.com"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="font-semibold text-white/60 underline-offset-4 transition-colors hover:text-white hover:underline"
                        >
                            SysUp360
                        </a>
                    </p>
                </div>
            </aside>

            <main className="relative flex items-center justify-center px-6 py-12 sm:px-10">
                <div className="mesh-backdrop animate-fade-in absolute inset-0 -z-10 opacity-60 lg:hidden" />

                <div className="animate-fade-in-up w-full max-w-sm space-y-8">
                    <div className="space-y-4">
                        <Link
                            href={home()}
                            className="inline-flex items-center gap-2.5 lg:hidden"
                        >
                            <BrandMark className="size-9" />
                            <span className="text-sm font-semibold">
                                {name}
                            </span>
                        </Link>

                        <div className="space-y-1.5">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {title}
                            </h1>
                            <p className="text-muted-foreground text-sm">
                                {description}
                            </p>
                        </div>
                    </div>

                    {children}

                    <PoweredBy className="text-center lg:hidden" />
                </div>
            </main>
        </div>
    );
}

