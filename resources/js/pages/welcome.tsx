import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    MessageSquare,
    Phone,
    ShieldCheck,
    Wallet,
} from 'lucide-react';
import BrandMark from '@/components/brand-mark';
import SignalOrbit from '@/components/illustrations/signal-orbit';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard, login, register } from '@/routes';

export default function Welcome() {
    const { auth, name } = usePage().props;

    return (
        <>
            <Head title="Welcome" />

            <div className="relative min-h-svh overflow-hidden">
                <div className="mesh-backdrop pointer-events-none absolute inset-0 -z-10 opacity-70" />
                <div className="grid-pattern pointer-events-none absolute inset-0 -z-10 opacity-30 [mask-image:radial-gradient(ellipse_at_top,black,transparent_72%)]" />

                <header className="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-6 py-5">
                    <div className="flex items-center gap-2.5">
                        <BrandMark className="size-9" />
                        <span className="text-sm font-semibold tracking-tight">
                            {name}
                        </span>
                    </div>

                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <Button asChild>
                                <Link href={dashboard()}>
                                    Open dashboard
                                    <ArrowRight />
                                </Link>
                            </Button>
                        ) : (
                            <>
                                <Button variant="ghost" asChild>
                                    <Link href={login()}>Log in</Link>
                                </Button>
                                <Button asChild>
                                    <Link href={register()}>
                                        Get started
                                        <ArrowRight />
                                    </Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>

                <main className="mx-auto w-full max-w-6xl px-6 pb-20">
                    <section className="grid items-center gap-12 py-10 lg:grid-cols-[1.05fr_0.95fr] lg:py-16">
                        <div className="animate-fade-in-up space-y-6">
                            <span className="bg-brand-soft text-accent-foreground ring-brand/20 inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium ring-1">
                                <span className="bg-brand flex size-1.5 rounded-full" />
                                Voice &amp; SMS broadcasting
                            </span>

                            <h1 className="text-4xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-5xl">
                                One workspace for every{' '}
                                <span className="brand-text">
                                    customer conversation
                                </span>
                                .
                            </h1>

                            <p className="text-muted-foreground max-w-xl text-base leading-relaxed">
                                Draft a campaign, reserve the credit, get it
                                approved and launch voice or SMS at scale — then
                                watch delivery, spend and consent land in real
                                time.
                            </p>

                            <div className="flex flex-wrap items-center gap-3">
                                {auth.user ? (
                                    <Button size="lg" asChild>
                                        <Link href={dashboard()}>
                                            Open dashboard
                                            <ArrowRight />
                                        </Link>
                                    </Button>
                                ) : (
                                    <>
                                        <Button size="lg" asChild>
                                            <Link href={register()}>
                                                Create an account
                                                <ArrowRight />
                                            </Link>
                                        </Button>
                                        <Button
                                            size="lg"
                                            variant="outline"
                                            asChild
                                        >
                                            <Link href={login()}>
                                                I already have one
                                            </Link>
                                        </Button>
                                    </>
                                )}
                            </div>

                            <dl className="grid max-w-md grid-cols-3 gap-4 border-t border-border/60 pt-6">
                                {[
                                    { label: 'Channels', value: 'Voice + SMS' },
                                    { label: 'Billing', value: 'GHS wallet' },
                                    { label: 'Tracking', value: 'Per recipient' },
                                ].map((item) => (
                                    <div key={item.label}>
                                        <dt className="text-muted-foreground text-[11px] font-semibold tracking-[0.14em] uppercase">
                                            {item.label}
                                        </dt>
                                        <dd className="mt-1 text-sm font-medium">
                                            {item.value}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </div>

                        <div className="relative mx-auto w-full max-w-md">
                            <div className="animate-float relative">
                                <SignalOrbit className="text-brand/70 w-full" />
                            </div>

                            <div className="surface-glass animate-fade-in-up absolute -top-2 left-0 rounded-xl p-3 shadow-lg ring-1 ring-border/60 [animation-delay:200ms]">
                                <div className="flex items-center gap-2.5">
                                    <span className="bg-signal flex size-8 items-center justify-center rounded-lg text-white">
                                        <MessageSquare className="size-4" />
                                    </span>
                                    <div>
                                        <p className="text-xs font-semibold">
                                            SMS campaign
                                        </p>
                                        <p className="text-muted-foreground text-[11px]">
                                            Delivery confirmed per recipient
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div className="surface-glass animate-fade-in-up absolute right-0 bottom-6 rounded-xl p-3 shadow-lg ring-1 ring-border/60 [animation-delay:320ms]">
                                <div className="flex items-center gap-2.5">
                                    <span className="brand-gradient flex size-8 items-center justify-center rounded-lg text-white">
                                        <Phone className="size-4" />
                                    </span>
                                    <div>
                                        <p className="text-xs font-semibold">
                                            Voice blast
                                        </p>
                                        <p className="text-muted-foreground text-[11px]">
                                            Caller ID · retries · max length
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="grid gap-4 sm:grid-cols-3">
                        {[
                            {
                                icon: ShieldCheck,
                                title: 'Approval workflow',
                                copy: 'Draft, submit and approve every campaign with a full audit trail of who did what.',
                            },
                            {
                                icon: Wallet,
                                title: 'Prepaid GHS wallet',
                                copy: 'Credit is reserved when a campaign is submitted and captured only when it launches.',
                            },
                            {
                                icon: MessageSquare,
                                title: 'Consent by default',
                                copy: 'Consent and do-not-contact flags travel with every recipient, on every channel.',
                            },
                        ].map((feature, index) => (
                            <div
                                key={feature.title}
                                className="card-lift animate-fade-in-up bg-card rounded-xl border border-border/70 p-5 shadow-sm"
                                style={{ animationDelay: `${index * 70}ms` }}
                            >
                                <span className="brand-gradient-soft ring-brand/15 text-brand mb-3 flex size-10 items-center justify-center rounded-xl ring-1">
                                    <feature.icon className="size-5" />
                                </span>
                                <p className="font-medium">{feature.title}</p>
                                <p className="text-muted-foreground mt-1 text-sm leading-relaxed">
                                    {feature.copy}
                                </p>
                            </div>
                        ))}
                    </section>

                    <section className="mt-12 grid gap-4 sm:grid-cols-2">
                        {[
                            {
                                icon: Phone,
                                title: 'Voice',
                                copy: 'Scripted outbound calls with caller ID, retry policy and a maximum duration per call.',
                                accent: 'brand-gradient',
                            },
                            {
                                icon: MessageSquare,
                                title: 'SMS',
                                copy: 'Approved sender IDs, part-counted copy and a delivery cost you can forecast up front.',
                                accent: 'bg-signal',
                            },
                        ].map((channel) => (
                            <div
                                key={channel.title}
                                className="bg-card relative overflow-hidden rounded-2xl border border-border/70 p-6"
                            >
                                <span
                                    className={cn(
                                        'absolute -top-16 -right-10 size-40 rounded-full opacity-20 blur-2xl',
                                        channel.accent,
                                    )}
                                />

                                <div className="relative space-y-2">
                                    <span
                                        className={cn(
                                            'flex size-10 items-center justify-center rounded-xl text-white',
                                            channel.accent,
                                        )}
                                    >
                                        <channel.icon className="size-5" />
                                    </span>
                                    <p className="text-lg font-semibold">
                                        {channel.title}
                                    </p>
                                    <p className="text-muted-foreground text-sm leading-relaxed">
                                        {channel.copy}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </section>
                </main>

                <footer className="text-muted-foreground mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-2 border-t border-border/60 px-6 py-6 text-xs">
                    <span>
                        © {new Date().getFullYear()} {name}
                    </span>
                    <span>Voice &amp; SMS delivery, accounted for.</span>
                </footer>
            </div>
        </>
    );
}
