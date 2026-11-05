import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Mail,
    MessageSquare,
    Phone,
    ShieldCheck,
    Wallet,
} from 'lucide-react';
import SignalOrbit from '@/components/illustrations/signal-orbit';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard, pricing } from '@/routes';
import { create as clientSignup } from '@/routes/client-registration';

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Welcome" />

            <section className="grid items-center gap-12 py-10 lg:grid-cols-[1.05fr_0.95fr] lg:py-16">
                <div className="animate-fade-in-up space-y-6">
                    <span className="inline-flex items-center gap-2 rounded-full bg-brand-soft px-3 py-1 text-xs font-medium text-accent-foreground ring-1 ring-brand/20">
                        <span className="flex size-1.5 rounded-full bg-brand" />
                        SMS, voice &amp; email broadcasting
                    </span>

                    <h1 className="text-4xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-5xl">
                        One workspace for every{' '}
                        <span className="brand-text">
                            customer conversation
                        </span>
                        .
                    </h1>

                    <p className="max-w-xl text-base leading-relaxed text-muted-foreground">
                        Draft a campaign, reserve the credit, get it approved
                        and launch SMS, voice or email at scale — then watch
                        delivery and spend land in real time.
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
                                    <Link href={clientSignup()}>
                                        Create an account
                                        <ArrowRight />
                                    </Link>
                                </Button>
                                <Button size="lg" variant="outline" asChild>
                                    <Link href={pricing()}>
                                        See the price list
                                    </Link>
                                </Button>
                            </>
                        )}
                    </div>

                    <dl className="grid max-w-md grid-cols-3 gap-4 border-t border-border/60 pt-6">
                        {[
                            { label: 'Channels', value: 'SMS · Voice · Email' },
                            { label: 'Billing', value: 'GHS wallet' },
                            { label: 'Tracking', value: 'Per recipient' },
                        ].map((item) => (
                            <div key={item.label}>
                                <dt className="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase">
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
                    <div className="relative animate-float">
                        <SignalOrbit className="w-full text-brand/70" />
                    </div>

                    <div className="absolute -top-2 left-0 animate-fade-in-up rounded-xl surface-glass p-3 shadow-lg ring-1 ring-border/60 [animation-delay:200ms]">
                        <div className="flex items-center gap-2.5">
                            <span className="flex size-8 items-center justify-center rounded-lg bg-signal text-white">
                                <MessageSquare className="size-4" />
                            </span>
                            <div>
                                <p className="text-xs font-semibold">
                                    SMS campaign
                                </p>
                                <p className="text-[11px] text-muted-foreground">
                                    Delivery confirmed per recipient
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="absolute top-8 right-0 animate-fade-in-up rounded-xl surface-glass p-3 shadow-lg ring-1 ring-border/60 [animation-delay:260ms]">
                        <div className="flex items-center gap-2.5">
                            <span className="flex size-8 items-center justify-center rounded-lg bg-brand-soft text-brand ring-1 ring-brand/20">
                                <Mail className="size-4" />
                            </span>
                            <div>
                                <p className="text-xs font-semibold">
                                    Email campaign
                                </p>
                                <p className="text-[11px] text-muted-foreground">
                                    Opens and bounces per contact
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="absolute right-0 bottom-6 animate-fade-in-up rounded-xl surface-glass p-3 shadow-lg ring-1 ring-border/60 [animation-delay:320ms]">
                        <div className="flex items-center gap-2.5">
                            <span className="flex size-8 items-center justify-center rounded-lg brand-gradient text-white">
                                <Phone className="size-4" />
                            </span>
                            <div>
                                <p className="text-xs font-semibold">
                                    Voice blast
                                </p>
                                <p className="text-[11px] text-muted-foreground">
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
                        title: 'DND built in',
                        copy: 'Every number can be added to the DND list, which blocks SMS, voice and email before a single message is sent.',
                    },
                ].map((feature, index) => (
                    <div
                        key={feature.title}
                        className="card-lift animate-fade-in-up rounded-xl border border-border/70 bg-card p-5 shadow-sm"
                        style={{ animationDelay: `${index * 70}ms` }}
                    >
                        <span className="mb-3 flex size-10 items-center justify-center rounded-xl brand-gradient-soft text-brand ring-1 ring-brand/15">
                            <feature.icon className="size-5" />
                        </span>
                        <p className="font-medium">{feature.title}</p>
                        <p className="mt-1 text-sm leading-relaxed text-muted-foreground">
                            {feature.copy}
                        </p>
                    </div>
                ))}
            </section>

            <section className="mt-12">
                <div className="mb-4 flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h2 className="text-lg font-semibold tracking-tight">
                            Three channels, one audience
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Build the list once, then reach it by text, by call
                            or by email — with the same budget and the same
                            reports.
                        </p>
                    </div>
                    <Button variant="link" asChild className="h-auto px-0">
                        <Link href={pricing()}>
                            View the price list
                            <ArrowRight />
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    {[
                        {
                            icon: MessageSquare,
                            title: 'SMS',
                            copy: 'Approved sender IDs, part-counted copy and a delivery cost you can forecast up front.',
                            accent: 'bg-signal',
                            iconClassName: 'text-white',
                        },
                        {
                            icon: Phone,
                            title: 'Voice',
                            copy: 'Scripted outbound calls with caller ID, retry policy and a maximum duration per call.',
                            accent: 'brand-gradient',
                            iconClassName: 'text-white',
                        },
                        {
                            icon: Mail,
                            title: 'Email',
                            copy: 'Branded subject lines and bodies, bounce handling and per-recipient delivery tracking.',
                            accent: 'bg-brand-soft',
                            iconClassName: 'text-brand',
                        },
                    ].map((channel) => (
                        <div
                            key={channel.title}
                            className="relative overflow-hidden rounded-2xl border border-border/70 bg-card p-6"
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
                                        'flex size-10 items-center justify-center rounded-xl',
                                        channel.accent,
                                        channel.iconClassName,
                                    )}
                                >
                                    <channel.icon className="size-5" />
                                </span>
                                <p className="text-lg font-semibold">
                                    {channel.title}
                                </p>
                                <p className="text-sm leading-relaxed text-muted-foreground">
                                    {channel.copy}
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
            </section>
        </>
    );
}
